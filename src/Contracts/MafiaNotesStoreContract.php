<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Contracts;

use BAGArt\TelegramBotMafia\Core\Enums\MarkKind;

/**
 * Per-player pencil marks ("notes pad"), deliberately OUTSIDE the game
 * snapshot: notes never affect game state resolution and never bump the
 * game's public rev.
 *
 * Concurrency model: every operation is atomic per (roomId, userId) key and
 * MUST NOT take the game lock. Notes are single-writer (their owner), so
 * plain compare-and-set-free value ops suffice — this makes a Redis
 * implementation trivial (HSET/HDEL/GET/INCR on pure values, no behavior
 * stored).
 *
 * Lifecycle: callers wipe rooms at game end (wipeRoom); the TTL story
 * ("game end + grace") is backend-specific — Redis sets native key TTLs,
 * the in-memory implementation exposes a timestamp-based prune().
 */
interface MafiaNotesStoreContract
{
    /**
     * Flips the mark kind for the seat; returns the resulting state
     * (true = mark present after the call).
     */
    public function toggle(string $roomId, string $userId, int $seat, MarkKind $kind): bool;

    /**
     * Replaces all marks of the seat with exactly the given kinds.
     *
     * @param  list<MarkKind>  $kinds
     */
    public function set(string $roomId, string $userId, int $seat, array $kinds): void;

    /**
     * Removes one kind from the seat, or every mark of the seat when
     * $kind is null.
     */
    public function clear(string $roomId, string $userId, int $seat, ?MarkKind $kind = null): void;

    /** Drops notes of all users in the room. */
    public function wipeRoom(string $roomId): void;

    /**
     * @return array<int, list<MarkKind>> seat => mark kinds (only seats
     *                                     holding at least one mark)
     */
    public function marks(string $roomId, string $userId): array;

    /**
     * Monotonic per-(roomId, userId) counter bumped on every write of that
     * user's notes (long-poll wake key; independent across users).
     */
    public function notesRev(string $roomId, string $userId): int;
}
