<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Presentation;

use BAGArt\TelegramBotMafia\Contracts\PresenterContract;
use BAGArt\TelegramBotMafia\Core\Enums\GameResultEnum;
use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\GameSnapshot;
use BAGArt\TelegramBotMafia\Core\NightReport;
use BAGArt\TelegramBotMafia\Core\RoleCatalog;
use BAGArt\TelegramBotMafia\Core\VoteOutcome;
use BAGArt\TelegramBotMafia\I18n\LangPack;
use BAGArt\TelegramBotMafia\Support\CallbackData;

/**
 * Group-chat skin: public announcements, public voting board. Hidden info
 * never renders here (privacy by layout).
 */
final class GroupPresenter implements PresenterContract
{
    public function __construct(
        private readonly LangPack $lang,
        private readonly GameCardRenderer $cards,
    ) {}

    /** @return list<SendPlan> */
    public function phaseAnnounce(GameSnapshot $snapshot): array
    {
        if ($snapshot->chatId === null) {
            return [];
        }
        $chat = (string) $snapshot->chatId;

        return match ($snapshot->phase) {
            PhaseEnum::Night => [new SendPlan($chat, $this->lang->t('night.phase_announce', escape: false))],
            PhaseEnum::DayDiscussion => [new SendPlan($chat,
                $this->lang->t('day.phase_announce', ['phase' => $snapshot->dayNumber], escape: false)."\n"
                .$this->lang->t('day.discussion_hint'))],
            PhaseEnum::DayVoting => [$this->voteBoard($snapshot)],
            default => [],
        };
    }

    /** @return list<SendPlan> */
    public function roleDealt(GameSnapshot $snapshot, int $seat): array
    {
        return []; // roles are private — handled by InterfacePresenter
    }

    /** @return list<SendPlan> */
    public function morning(GameSnapshot $snapshot, NightReport $report): array
    {
        if ($snapshot->chatId === null || $snapshot->seats === []) {
            return [];
        }
        $chat = (string) $snapshot->chatId;
        if ($report->deaths === []) {
            return [new SendPlan($chat, $this->lang->t('night.no_kill_result', escape: false))];
        }
        $plans = [];
        foreach ($report->deaths as $seatNum) {
            $victim = $snapshot->seat($seatNum);
            if ($victim !== null) {
                $plans[] = new SendPlan($chat,
                    $this->lang->t('night.death_result', ['name' => $victim->name], escape: false));
            }
        }

        return $plans;
    }

    /** @return list<SendPlan> */
    public function voteClosed(GameSnapshot $snapshot, VoteOutcome $outcome): array
    {
        if ($snapshot->chatId === null) {
            return [];
        }
        $chat = (string) $snapshot->chatId;
        if ($outcome->eliminatedSeat !== null) {
            $victim = $snapshot->seat($outcome->eliminatedSeat);
            $roleLine = '';
            if ($victim?->role !== null) {
                $roleLine = "\n".$this->lang->t('day.eliminated_was', [
                    'role' => $this->lang->t('roles.'.$victim->role.'.name'),
                ], escape: false);
            }

            return [new SendPlan($chat,
                $this->lang->t('day.eliminated', ['name' => $victim?->name ?? '?'], escape: false).$roleLine)];
        }
        if ($outcome->requiresRevote()) {
            $names = implode(', ', array_map(
                fn (int $s) => $snapshot->seat($s)?->name ?? (string) $s,
                $outcome->tieCandidates
            ));

            return [new SendPlan($chat, $this->lang->t('day.tie_revote', ['names' => $names]))];
        }

        return [new SendPlan($chat, $this->lang->t('day.no_elimination', escape: false))];
    }

    /** @return list<SendPlan> */
    public function gameEnded(GameSnapshot $snapshot): array
    {
        if ($snapshot->chatId === null) {
            return [];
        }
        $chat = (string) $snapshot->chatId;
        $header = match ($snapshot->result) {
            GameResultEnum::TownWon => $this->lang->t('end.town_win', escape: false),
            GameResultEnum::MafiaWon => $this->lang->t('end.mafia_win', escape: false),
            GameResultEnum::SoloWon => $this->lang->t('end.solo_win',
                ['name' => $this->lastSoloName($snapshot)], escape: false),
            GameResultEnum::SatanistWon => $this->lang->t('end.satanist_win',
                ['name' => $this->lastSoloName($snapshot)], escape: false),
            default => $this->lang->t('end.header', escape: false),
        };
        $roles = [$header, '', $this->lang->t('end.roles_reveal_header', escape: false)];
        foreach ($snapshot->seats as $seat) {
            $roles[] = $this->lang->t('end.roles_reveal_line', [
                'emoji' => RoleCatalog::emoji((string) $seat->role),
                'name' => $seat->name,
                'role' => $this->lang->t('roles.'.((string) $seat->role).'.name'),
            ], escape: false);
        }

        return [new SendPlan($chat, implode("\n", $roles), Keyboards::single([
            ['label' => $this->lang->t('end.rematch_button'), 'callback' => CallbackData::encode('again', $snapshot->gameId)],
        ]))];
    }

    private function voteBoard(GameSnapshot $snapshot): SendPlan
    {
        $labels = [];
        $alive = [];
        foreach ($snapshot->aliveSeats() as $seat) {
            $alive[] = $seat->seat;
            $labels[$seat->seat] = $this->lang->t('day.seat_button', [
                'seat' => $seat->seat, 'name' => $seat->name,
            ]);
        }
        $rows = Keyboards::seatGrid('v', $snapshot->gameId, $alive, $labels, 2, [[
            ['label' => $this->lang->t('day.abstain_button'), 'callback' => CallbackData::encode('abstain', $snapshot->gameId)],
        ]]);

        return new SendPlan(
            (string) $snapshot->chatId,
            $this->lang->t('day.voting_open', [], escape: false),
            $rows
        );
    }

    private function lastSoloName(GameSnapshot $snapshot): string
    {
        foreach ($snapshot->aliveSeats() as $seat) {
            return $seat->name;
        }

        return '?';
    }
}
