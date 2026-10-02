<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Contracts;

use BAGArt\TelegramBotMafia\Quickplay\QuickplayQueueEntry;

/**
 * Bot-scoped matchmaking queue. Players join; when the threshold is met a
 * room is created and started automatically. Production uses a Redis sorted
 * set; tests use an in-memory implementation.
 */
interface QuickplayQueueContract
{
    /**
     * Add a player to the queue for the given bot. Idempotent: if the player
     * is already queued, their position is refreshed (score updated).
     *
     * @return int 0-based position in queue (0 = first)
     */
    public function join(string $botId, string $userId, string $name): int;

    /**
     * Remove a player from the queue. No-op if not queued.
     */
    public function cancel(string $botId, string $userId): void;

    /**
     * Return all entries for the given bot, ordered by join time (oldest first).
     *
     * @return list<QuickplayQueueEntry>
     */
    public function entries(string $botId): array;

    /**
     * Remove all entries older than $ olderThanSeconds from now for the given bot.
     * Returns the removed entries so callers can notify them.
     *
     * @return list<QuickplayQueueEntry>
     */
    public function drainExpired(string $botId, int $olderThanSeconds): array;

    /**
     * Number of players currently queued for this bot.
     */
    public function count(string $botId): int;

    /**
     * Check if a player is already queued for this bot.
     */
    public function contains(string $botId, string $userId): bool;
}
