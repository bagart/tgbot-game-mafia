<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core;

/**
 * Resolves a submitted night into outcomes following roles.json
 * night_resolution_order. Pure: snapshot in (unchanged), report out.
 */
final class NightResolver
{
    public function resolve(GameSnapshot $snapshot): NightReport
    {
        $bySeat = [];
        foreach ($snapshot->nightActions as $a) {
            $bySeat[$a->actorSeat] = $a;
        }

        $blocked = $this->applyBlocks($snapshot, $bySeat);

        $savedSeat = $this->resolveHeal($snapshot, $bySeat, $blocked);
        $protectedBy = $this->resolveProtection($snapshot, $bySeat, $blocked);
        [$deaths, $satanistWon, $witnessName] = $this->resolveKills(
            $snapshot, $bySeat, $blocked, $savedSeat, $protectedBy
        );
        $checkResults = $this->resolveInfo($snapshot, $bySeat, $blocked);
        $elderSaved = $this->elderSavedSeats($deaths, $snapshot);

        return new NightReport(
            deaths: array_values(array_unique(array_diff($deaths, $elderSaved))),
            savedSeat: $savedSeat,
            elderSaved: array_values(array_unique($elderSaved)),
            satanistSacrificed: $satanistWon,
            witnessSeesName: $witnessName,
            checkResults: $checkResults,
        );
    }

    /**
     * Mafia bloc decision (majority, godfather breaks ties) + solo killers.
     *
     * @param  array<int, NightAction>  $bySeat  @param  list<int>  $blocked
     * @return array{0: list<int>, 1: bool, 2: ?string} deaths, satanist flag, witness-visible killer
     */
    private function resolveKills(
        GameSnapshot $snapshot,
        array $bySeat,
        array $blocked,
        ?int $savedSeat,
        array $protectedBy,
    ): array {
        $deaths = [];
        $satanistWon = false;
        $witnessName = null;

        foreach ($this->killOrders($snapshot, $bySeat, $blocked) as [$actor, $target]) {
            if ($target === null || $target === $actor->seat) {
                continue;
            }
            $victim = $snapshot->seat($target);
            if ($victim === null || ! $victim->alive) {
                continue;
            }
            if ($victim->role === 'bomzh') {
                continue; // no home to find at night
            }
            if ($savedSeat === $target) {
                continue;
            }
            if (isset($protectedBy[$target])) {
                // the bodyguard takes the bullet for their principal
                if (! in_array($protectedBy[$target], $deaths, true)) {
                    $deaths[] = $protectedBy[$target];
                    $witnessName ??= $actor->name;
                }
                unset($protectedBy[$target]);

                continue;
            }
            if (! in_array($target, $deaths, true)) {
                // elder hits land here too; resolver splits shields out afterwards
                $deaths[] = $target;
                $witnessName ??= $actor->name;
            }
            if ($victim->role === 'satanist'
                && in_array((string) $actor->role, RoleCatalog::mafiaTeamIds(), true)
            ) {
                $satanistWon = true;
            }
        }

        return [$deaths, $satanistWon, $witnessName];
    }

    /**
     * Ordered kill attempts: unified mafia bloc acts once (actor = first living
     * mafia member), then each solo killer.
     *
     * @param  array<int, NightAction>  $bySeat  @param  list<int>  $blocked
     * @return list<array{0: SeatState, 1: ?int}>
     */
    private function killOrders(GameSnapshot $snapshot, array $bySeat, array $blocked): array
    {
        $out = [];

        $votes = [];
        $godfatherVote = null;
        $blocActor = null;
        foreach ($snapshot->aliveSeats() as $s) {
            $role = (string) $s->role;
            if (! in_array($role, RoleCatalog::mafiaTeamIds(), true)) {
                continue;
            }
            $blocActor ??= $s;
            if (in_array($s->seat, $blocked, true) || ! isset($bySeat[$s->seat])) {
                continue;
            }
            $a = $bySeat[$s->seat];
            if ($role === 'godfather' && $a->targetSeat !== null) {
                $godfatherVote = $a->targetSeat;
            }
            if ($a->type === 'kill' && $a->targetSeat !== null) {
                $votes[] = $a->targetSeat;
            }
        }
        if ($blocActor !== null && $votes !== []) {
            $counts = array_count_values($votes);
            arsort($counts);
            $topCount = max($counts);
            $tied = array_keys(array_filter($counts, fn ($c) => $c === $topCount));
            $target = (count($tied) > 1 && $godfatherVote !== null)
                ? $godfatherVote
                : (int) $tied[0];
            $out[] = [$blocActor, $target];
        }

        foreach ($snapshot->aliveSeats() as $s) {
            if (! in_array((string) $s->role, RoleCatalog::soloKillerIds(), true)) {
                continue;
            }
            if (in_array($s->seat, $blocked, true) || ! isset($bySeat[$s->seat])) {
                continue;
            }
            $a = $bySeat[$s->seat];
            $out[] = [$s, $a->type === 'kill' ? $a->targetSeat : null];
        }

        return $out;
    }

    /**
     * @param  array<int, NightAction>  $bySeat  @param  list<int>  $blocked
     * @return array<int, int> victimSeat => bodyguardSeat
     */
    private function resolveProtection(GameSnapshot $snapshot, array $bySeat, array $blocked): array
    {
        $map = [];
        foreach ($this->actorsOf($snapshot, 'bodyguard', $bySeat, $blocked) as [$actor, $action]) {
            if ($action?->targetSeat !== null && $action->targetSeat !== $actor->seat) {
                $map[$action->targetSeat] = $actor->seat;
            }
        }

        return $map;
    }

    /** @param  array<int, NightAction>  $bySeat @param  list<int>  $blocked */
    private function resolveHeal(GameSnapshot $snapshot, array $bySeat, array $blocked): ?int
    {
        foreach ($this->actorsOf($snapshot, 'doctor', $bySeat, $blocked) as [$actor, $action]) {
            $t = $action?->targetSeat;
            if ($t === null) {
                continue;
            }
            if ($t === $actor->seat && $actor->selfHealLeft <= 0) {
                continue;
            }

            return $t; // one doctor per game (max_per_game=1); healing bomzh is wasted by design
        }

        return null;
    }

    /**
     * @param  array<int, NightAction>  $bySeat  @param  list<int>  $blocked
     * @return array<string, array<int, string>> actorSeat => kind => targetSeat => result
     */
    private function resolveInfo(GameSnapshot $snapshot, array $bySeat, array $blocked): array
    {
        $results = [];
        foreach ($this->actorsOf($snapshot, 'detective', $bySeat, $blocked) as [$actor, $action]) {
            $t = $action?->targetSeat === null ? null : $snapshot->seat($action->targetSeat);
            if ($t === null) {
                continue;
            }
            $reads = in_array((string) $t->role, RoleCatalog::mafiaTeamIds(), true) ? 'mafia' : 'innocent';
            $results[(string) $actor->seat]['alignment'][(string) $t->seat] = $reads;
        }
        foreach ($this->actorsOf($snapshot, 'journalist', $bySeat, $blocked) as [$actor, $action]) {
            $t = $action?->targetSeat === null ? null : $snapshot->seat($action->targetSeat);
            if ($t === null || $t->role === null) {
                continue;
            }
            $results[(string) $actor->seat]['exact'][(string) $t->seat] = (string) $t->role;
        }

        return $results;
    }

    /**
     * Elder shields that fired this night: victims who would have died.
     *
     * @param  list<int>  $deaths
     * @return list<int>
     */
    private function elderSavedSeats(array $deaths, GameSnapshot $snapshot): array
    {
        // deaths already exclude healed/protected targets; remaining ones hit elders
        $saved = [];
        foreach ($deaths as $seat) {
            $s = $snapshot->seat($seat);
            if ($s !== null && $s->elderShield) {
                $saved[] = $seat;
            }
        }

        return $saved;
    }

    /**
     * @param  array<int, NightAction>  $bySeat  @param  list<int>  $blocked
     * @return \Generator<int, array{0: SeatState, 1: ?NightAction}>
     */
    private function actorsOf(GameSnapshot $snapshot, string $roleId, array $bySeat, array $blocked): \Generator
    {
        foreach ($snapshot->aliveSeats() as $s) {
            if ((string) $s->role !== $roleId) {
                continue;
            }
            if (in_array($s->seat, $blocked, true)) {
                continue;
            }
            yield [$s, $bySeat[$s->seat] ?? null];
        }
    }

    /**
     * Escort visits cancel the target's tonight action (any role).
     *
     * @param  array<int, NightAction>  $bySeat
     * @return list<int> blocked seats
     */
    private function applyBlocks(GameSnapshot $snapshot, array $bySeat): array
    {
        $blocked = [];
        foreach ($this->actorsOf($snapshot, 'escort', $bySeat, []) as [$escort, $action]) {
            $t = $action?->targetSeat;
            if ($t !== null && $t !== $escort->seat) {
                $blocked[] = $t;
            }
        }

        return $blocked;
    }
}
