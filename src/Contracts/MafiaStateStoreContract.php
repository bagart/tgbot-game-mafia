<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Contracts;

use BAGArt\TelegramBotMafia\Core\GameSnapshot;

/**
 * Active-game persistence. Production: Redis (snapshot JSON + per-game lock).
 * MVP ships an in-memory implementation; snapshots are pure data, so the
 * storage backend can be swapped without touching the core.
 */
interface MafiaStateStoreContract
{
    public function saveSnapshot(GameSnapshot $snapshot): void;

    public function loadSnapshot(string $gameId): ?GameSnapshot;

    public function deleteSnapshot(string $gameId): void;

    /** Game id running in the given chat, if any (status running). */
    public function gameByChat(string $chatId): ?GameSnapshot;

    /** Game id where the user currently sits, if any (one active game per user). */
    public function gameByUser(string $userId): ?GameSnapshot;

    /** All non-ended games (sweep/deadline enforcement iterates these). */
    public function activeGames(): array;

    /**
     * Atomic claim of a pending "say" relay: returns true once, then clears.
     * The next DM message from this user is relayed into the game feed.
     */
    public function consumeSayLock(string $userId, string $gameId): bool;

    public function setSayLock(string $userId, string $gameId): void;

    /**
     * @param  list<string>  $userIds
     * @return array<string, bool> userId => confirmed
     */
    public function dmConfirmations(string $roomId, array $userIds): array;

    public function setDmConfirmed(string $roomId, string $userId): void;
}
