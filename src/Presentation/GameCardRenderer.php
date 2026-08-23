<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Presentation;

use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\GameSnapshot;
use BAGArt\TelegramBotMafia\Core\RoleCatalog;
use BAGArt\TelegramBotMafia\Core\SeatState;
use BAGArt\TelegramBotMafia\I18n\LangPack;

/**
 * Renders the live Game Card text. Pure; the same renderer serves both
 * presenters (group status post and private interface card).
 */
final class GameCardRenderer
{
    public function __construct(private readonly LangPack $lang) {}

    /** @param  int|null  $viewerSeat  private layer (role line, HUD) for the owner only */
    public function render(GameSnapshot $snapshot, ?int $viewerSeat = null): string
    {
        $lines = [$this->header($snapshot), '──────────────────────'];
        foreach ($snapshot->seats as $seat) {
            $markers = $this->seatMarkers($seat);
            $lines[] = $this->lang->t('lobby.player_row', [
                'seat' => $seat->seat,
                'name' => ($seat->isBot ? $this->lang->t('lobby.bot_marker').' ' : '').$seat->name,
            ]).$markers;
        }
        if ($viewerSeat !== null) {
            $me = $snapshot->seat($viewerSeat);
            if ($me?->role !== null) {
                $dead = $me->alive ? '' : $this->lang->t('interface.dead_mark');
                $lines[] = '';
                $lines[] = $this->lang->t('interface.card_you_line', [
                    'emoji' => RoleCatalog::emoji($me->role),
                    'role' => $this->roleName($me->role),
                    'dead_mark' => $dead,
                ], escape: false);
            }
        }

        return implode("\n", $lines);
    }

    private function header(GameSnapshot $snapshot): string
    {
        $phaseName = $this->lang->t('meta.phase_names.'.match ($snapshot->phase) {
            PhaseEnum::Setup => 'setup',
            PhaseEnum::Night => 'night',
            PhaseEnum::DayDiscussion => 'day_discussion',
            PhaseEnum::DayVoting => 'day_voting',
            PhaseEnum::Ended => 'ended',
        });
        $base = $this->lang->t('meta.game_signature', [
            'phase_emoji' => match ($snapshot->phase) {
                PhaseEnum::Night => '🌙',
                PhaseEnum::DayDiscussion, PhaseEnum::DayVoting => '☀️',
                default => '🎭',
            },
            'phase_name' => $phaseName,
        ], escape: false);

        return $base.' · '.$this->lang->t('extras2.phase_progress_chip', ['day' => $snapshot->dayNumber]);
    }

    private function seatMarkers(SeatState $seat): string
    {
        $out = '';
        if (! $seat->alive) {
            $out .= ' 💀';
        }
        if ($seat->missedVote && $seat->alive) {
            $out .= ' '.$this->lang->t('sleepy.badge');
        }

        return $out;
    }

    private function roleName(string $roleId): string
    {
        return (string) $this->lang->t('roles.'.$roleId.'.name', escape: false);
    }
}
