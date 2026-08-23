<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Rooms;

use BAGArt\TelegramBotMafia\Contracts\ClockContract;
use BAGArt\TelegramBotMafia\Contracts\MafiaStateStoreContract;
use BAGArt\TelegramBotMafia\Contracts\ProfileStoreContract;

/**
 * Join-time rules. Every refusal maps to a lang-pack reason key so the UI can
 * explain what to do next (no dead ends).
 */
final readonly class JoinGuard
{
    public function __construct(
        private MafiaStateStoreContract $store,
        private ProfileStoreContract $profiles,
        private ClockContract $clock,
    ) {}

    /** @return string|null null = allowed, otherwise lang key */
    public function check(Room $room, int $memberCount, string $userId): ?string
    {
        if ($room->status !== 'lobby') {
            return 'rooms.join_started_toast';
        }
        if ($memberCount >= $room->maxPlayers) {
            return 'rooms.join_full_toast';
        }
        if (($until = $this->profiles->frozenUntil($userId)) !== null && $until > $this->clock->now()) {
            return 'rooms.join_frozen_toast';
        }
        if ($this->store->gameByUser($userId) !== null) {
            return 'errors.already_in_other_game';
        }

        return null;
    }
}
