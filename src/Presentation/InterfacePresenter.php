<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Presentation;

use BAGArt\TelegramBotMafia\Contracts\PresenterContract;
use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\GameSnapshot;
use BAGArt\TelegramBotMafia\Core\NightReport;
use BAGArt\TelegramBotMafia\Core\RoleCatalog;
use BAGArt\TelegramBotMafia\Core\SeatState;
use BAGArt\TelegramBotMafia\Core\VoteOutcome;
use BAGArt\TelegramBotMafia\I18n\LangPack;
use BAGArt\TelegramBotMafia\Support\CallbackData;

/**
 * Private interface skin: per-human DM surfaces (role cards, night menus,
 * personal check results, game-card feed copies).
 */
final class InterfacePresenter implements PresenterContract
{
    public function __construct(
        private readonly LangPack $lang,
        private readonly GameCardRenderer $cards,
    ) {
    }

    /** @return list<SeatState> */
    public static function humanSeats(GameSnapshot $snapshot): array
    {
        return array_values(array_filter($snapshot->seats, fn (SeatState $s) => ! $s->isBot));
    }

    /** @return list<SendPlan> */
    public function phaseAnnounce(GameSnapshot $snapshot): array
    {
        $plans = [];
        foreach (self::humanSeats($snapshot) as $seat) {
            $plans[] = $this->cardFor($snapshot, $seat);
            if ($snapshot->phase === PhaseEnum::Night && $seat->alive && $this->hasAction($seat)) {
                $plans = [...$plans, ...$this->nightMenu($snapshot, $seat)];
            }
        }

        return $plans;
    }

    /** @return list<SendPlan> */
    public function roleDealt(GameSnapshot $snapshot, int $seat): array
    {
        $me = $snapshot->seat($seat);
        if ($me === null || $me->isBot || $me->role === null) {
            return [];
        }
        $text = implode("\n", [
            $this->lang->t('roles.your_role_header', escape: false),
            RoleCatalog::emoji($me->role).' '.$this->lang->t('roles.'.$me->role.'.name', escape: false),
            '',
            $this->lang->t('roles.'.$me->role.'.intro', [], escape: false),
            $this->lang->t('roles.'.$me->role.'.goal', [], escape: false),
        ]);
        if ($me->role === 'doctor') {
            $text .= "\n".$this->lang->t('roles.doctor.self_heal_note');
        }
        $teammates = $this->mafiaTeammatesText($snapshot, $me);
        if ($teammates !== null) {
            $text .= "\n".$teammates;
        }

        return [new SendPlan((string) $me->userId, $text)];
    }

    /** @return list<SendPlan> */
    public function morning(GameSnapshot $snapshot, NightReport $report): array
    {
        $plans = [];
        foreach (self::humanSeats($snapshot) as $seat) {
            foreach ($report->deaths as $deadSeat) {
                if ($deadSeat === $seat->seat) {
                    $plans[] = new SendPlan(
                        (string) $seat->userId,
                        $this->lang->t('errors.dead_no_actions_toast')
                    );
                }
            }
            $checks = $report->checkResults[(string) $seat->seat] ?? [];
            foreach (($checks['alignment'] ?? []) as $target => $verdict) {
                $targetName = $snapshot->seat((int) $target)?->name ?? $target;
                $key = $verdict === 'mafia' ? 'night.detective_result_mafia' : 'night.detective_result_clean';
                $plans[] = new SendPlan(
                    (string) $seat->userId,
                    $this->lang->t($key, ['name' => $targetName], escape: false)
                );
            }
            foreach (($checks['exact'] ?? []) as $target => $roleId) {
                $targetName = $snapshot->seat((int) $target)?->name ?? $target;
                $plans[] = new SendPlan((string) $seat->userId, $this->lang->t('night.journalist_result', [
                    'name' => $targetName, 'role' => $this->lang->t('roles.'.$roleId.'.name'),
                ], escape: false));
            }
        }
        if ($report->witnessSeesName !== null) {
            foreach ($snapshot->seats as $seat) {
                if ($seat->role === 'witness' && ! $seat->isBot) {
                    $plans[] = new SendPlan(
                        (string) $seat->userId,
                        $this->lang->t('night.witness_result', ['name' => $report->witnessSeesName], escape: false)
                    );
                }
            }
        }

        return $plans;
    }

    /** @return list<SendPlan> */
    public function voteClosed(GameSnapshot $snapshot, VoteOutcome $outcome): array
    {
        // public broadcast is GroupPresenter's job; interface players get the card refresh only
        return array_map(fn (SeatState $s) => $this->cardFor($snapshot, $s), self::humanSeats($snapshot));
    }

    /** @return list<SendPlan> */
    public function gameEnded(GameSnapshot $snapshot): array
    {
        return array_map(fn (SeatState $s) => new SendPlan(
            (string) $s->userId,
            $this->lang->t('end.header', escape: false)
        ), self::humanSeats($snapshot));
    }

    // ---- helpers ---------------------------------------------------------

    private function cardFor(GameSnapshot $snapshot, SeatState $viewer): SendPlan
    {
        return new SendPlan((string) $viewer->userId, $this->cards->render($snapshot, $viewer->seat));
    }

    private function hasAction(SeatState $seat): bool
    {
        $action = RoleCatalog::action((string) $seat->role);

        return $action !== null && in_array($action, ['kill', 'heal', 'block_action', 'protect', 'check_alignment', 'check_exact_role'], true);
    }

    /** @return list<SendPlan> */
    private function nightMenu(GameSnapshot $snapshot, SeatState $actor): array
    {
        $promptKey = match ($actor->role) {
            'mafia', 'godfather', 'turncoat' => 'night.prompt_mafia',
            'doctor' => 'night.prompt_doctor',
            'detective' => 'night.prompt_detective',
            'escort' => 'night.prompt_escort',
            'bodyguard' => 'night.prompt_bodyguard',
            'journalist' => 'night.prompt_journalist',
            default => null,
        };
        if ($promptKey === null) {
            return [];
        }
        $labels = [];
        $targets = [];
        $team = in_array((string) $actor->role, RoleCatalog::mafiaTeamIds(), true);
        foreach ($snapshot->aliveSeats() as $seat) {
            if ($seat->seat === $actor->seat && $actor->role !== 'doctor') {
                continue; // self-target only for doctor
            }
            if ($team && $actor->role !== 'godfather'
                && in_array((string) $seat->role, RoleCatalog::mafiaTeamIds(), true)) {
                continue; // mafia cannot target teammates (godfather sees all as tools anyway)
            }
            $targets[] = $seat->seat;
            $labels[$seat->seat] = $this->lang->t('day.seat_button', [
                'seat' => $seat->seat, 'name' => $seat->name,
            ]);
        }
        $rows = Keyboards::seatGrid('n', $snapshot->gameId, $targets, $labels, 2, [[
            ['label' => $this->lang->t('common.skip_action'), 'callback' => CallbackData::encode('skipn', $snapshot->gameId)],
        ]]);

        return [new SendPlan((string) $actor->userId, $this->lang->t($promptKey, [], escape: false), $rows)];
    }

    private function mafiaTeammatesText(GameSnapshot $snapshot, SeatState $me): ?string
    {
        if (! in_array((string) $me->role, RoleCatalog::mafiaTeamIds(), true)) {
            return null;
        }
        $mates = array_filter(
            $snapshot->seats,
            fn (SeatState $s) => $s->seat !== $me->seat
                && in_array((string) $s->role, RoleCatalog::mafiaTeamIds(), true)
        );
        if ($mates === []) {
            return $this->lang->t('roles.lone_mafia', escape: false);
        }
        $names = implode(', ', array_map(fn (SeatState $s) => $s->name, $mates));

        return $this->lang->t('roles.teammates_mafia', ['names' => $names], escape: false);
    }
}
