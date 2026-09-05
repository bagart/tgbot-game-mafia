<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core;

/**
 * Builds the actual role multiset for N joined players from the count-based
 * preset, filtered through host checkboxes, then validated against
 * constraints. Deterministic given inputs.
 */
final class RoleSetBuilder
{
    /**
     * @param  list<string>  $checkedRoles  host-selected optional roles
     * @return BuildResult
     */
    public function build(int $playerCount, array $checkedRoles): object
    {
        $c = RoleCatalog::constraints();
        $n = max((int) $c['players_min'], min($playerCount, (int) $c['players_max']));
        $checked = array_values(array_unique($checkedRoles));

        // Preset for the effective count keeps the "setting scales with size" rule.
        $base = RoleCatalog::presetsFor($n);
        $allowed = array_merge($checked, RoleCatalog::mandatory());
        $roles = array_values(array_filter($base, fn ($r) => in_array($r, $allowed, true)));

        $roles = $this->ensureMandatory($roles);

        $roles = $this->capByMaxPerGame($roles);

        $roles = $this->capMafiaShare($roles, $n);
        $roles = $this->capSoloKillers($roles);

        [$roles, $dropped] = $this->fitIntoSeats($roles, $n);

        while (count($roles) < $n) {
            $roles[] = 'civilian';
        }

        $reason = $this->validateFinal($roles, $n);

        return new BuildResult($reason === null, $reason, $roles, $dropped);
    }

    /** @param  list<string>  $roles @return list<string> */
    private function ensureMandatory(array $roles): array
    {
        foreach (RoleCatalog::mandatory() as $m) {
            if (! in_array($m, $roles, true)) {
                // insert after existing mafia entries to keep presets ordered
                $roles[] = $m;
            }
        }

        return $roles;
    }

    /** @param  list<string>  $roles @return list<string> */
    private function capByMaxPerGame(array $roles): array
    {
        $seen = [];
        $out = [];
        foreach ($roles as $r) {
            $seen[$r] = ($seen[$r] ?? 0) + 1;
            if (! RoleCatalog::exists($r) || $seen[$r] <= RoleCatalog::maxPerGame($r)) {
                $out[] = $r;
            }
        }

        return $out;
    }

    /** @param  list<string>  $roles @return list<string> */
    private function capMafiaShare(array $roles, int $n): array
    {
        $divisor = (int) RoleCatalog::constraints()['mafia_share_max_divisor'];
        $maxMafia = max(1, intdiv($n, $divisor));
        $mafiaCount = count(array_filter($roles, fn ($r) => in_array($r, RoleCatalog::mafiaTeamIds(), true)));
        while ($mafiaCount > $maxMafia) {
            $idx = $this->lastIndexOfAny($roles, ['mafia']);
            if ($idx === null) {
                break;
            }
            array_splice($roles, $idx, 1);
            $mafiaCount--;
        }

        return $roles;
    }

    /** @param  list<string>  $roles @return list<string> */
    private function capSoloKillers(array $roles): array
    {
        $maxSolo = (int) RoleCatalog::constraints()['solo_killer_max'];
        $solo = count(array_filter($roles, fn ($r) => in_array($r, RoleCatalog::soloKillerIds(), true)));
        while ($solo > $maxSolo) {
            $idx = $this->lastIndexOfAny($roles, RoleCatalog::soloKillerIds());
            if ($idx === null) {
                break;
            }
            array_splice($roles, $idx, 1);
            $solo--;
        }

        return $roles;
    }

    /**
     * Trim lowest-priority specials (tail of the preset order) so at least
     * civilians_min civilians remain.
     *
     * @param  list<string>  $roles  @return array{0:list<string>,1:list<string>}
     */
    private function fitIntoSeats(array $roles, int $n): array
    {
        $minCiv = (int) RoleCatalog::constraints()['civilians_min'];
        $dropped = [];
        while (count($roles) + $minCiv > $n && count($roles) > 2) {
            $idx = $this->lastIndexOfOptionalSpecial($roles);
            if ($idx === null) {
                break;
            }
            $dropped[] = $roles[$idx];
            array_splice($roles, $idx, 1);
        }

        return [$roles, $dropped];
    }

    /** @param  list<string>  $roles @return string|null */
    private function validateFinal(array $roles, int $n): ?string
    {
        $mafia = count(array_filter($roles, fn ($r) => in_array($r, RoleCatalog::mafiaTeamIds(), true)));
        if ($mafia < 1) {
            return 'no_mafia';
        }
        $divisor = (int) RoleCatalog::constraints()['mafia_share_max_divisor'];
        if ($mafia > intdiv($n, $divisor)) {
            return 'mafia_share';
        }
        foreach ($roles as $r) {
            if (! RoleCatalog::exists($r)) {
                return "unknown_role:{$r}";
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $roles  @param  list<string>  $any
     */
    private function lastIndexOfAny(array $roles, array $any): ?int
    {
        for ($i = count($roles) - 1; $i >= 0; $i--) {
            if (in_array($roles[$i], $any, true)) {
                return $i;
            }
        }

        return null;
    }

    /** Drop tail-most special that is not mandatory and not plain mafia filler. */
    private function lastIndexOfOptionalSpecial(array $roles): ?int
    {
        $mandatory = RoleCatalog::mandatory();
        for ($i = count($roles) - 1; $i >= 0; $i--) {
            $r = $roles[$i];
            if (in_array($r, $mandatory, true) || $r === 'civilian') {
                continue;
            }
            if (in_array($r, RoleCatalog::mafiaTeamIds(), true)) {
                // keep at least one mafia-team member
                $teamCount = count(array_filter($roles, fn ($x) => in_array($x, RoleCatalog::mafiaTeamIds(), true)));
                if ($teamCount <= 1) {
                    continue;
                }
            }

            return $i;
        }

        return null;
    }
}

/** Immutable builder outcome (kept out of arrays for readable call sites). */
final readonly class BuildResult
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $dropped
     */
    public function __construct(
        public bool $ok,
        public ?string $reasonKey,
        public array $roles,
        public array $dropped = [],
    ) {
    }
}
