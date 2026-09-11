<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\MafiaNotesStoreContract;
use BAGArt\TelegramBotMafia\Core\Enums\MarkKind;
use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed pencil marks store.
 *
 * Key patterns:
 *   mafia:notes:{roomId}:{userId}       — Hash of seat → comma-separated MarkKind values
 *   mafia:notes:rev:{roomId}:{userId}   — Integer revision counter (INCR on every write)
 *   mafia:notes:rooms:{roomId}          — Set of userIds with notes (for wipeRoom)
 *
 * TTL: 4 hours (same as game snapshot).
 */
final class RedisMafiaNotesStore implements MafiaNotesStoreContract
{
    private const string PREFIX = 'mafia:notes:';
    private const int TTL = 14400; // 4 hours

    public function toggle(string $roomId, string $userId, int $seat, MarkKind $kind): bool
    {
        $key = self::PREFIX . "{$roomId}:{$userId}";
        $current = Redis::hget($key, (string) $seat);

        $kinds = $current !== false ? explode(',', (string) $current) : [];
        $kindValue = $kind->value;

        if (in_array($kindValue, $kinds, true)) {
            // Remove the kind.
            $kinds = array_values(array_filter($kinds, fn (string $k) => $k !== $kindValue));
        } else {
            // Add the kind.
            $kinds[] = $kindValue;
        }

        $pipe = Redis::pipeline();
        if ($kinds === []) {
            $pipe->hdel($key, (string) $seat);
        } else {
            $pipe->hset($key, (string) $seat, implode(',', $kinds));
        }
        $pipe->expire($key, self::TTL);
        $pipe->incr(self::PREFIX . "rev:{$roomId}:{$userId}");
        $pipe->expire(self::PREFIX . "rev:{$roomId}:{$userId}", self::TTL);
        $pipe->sAdd(self::PREFIX . "rooms:{$roomId}", $userId);
        $pipe->expire(self::PREFIX . "rooms:{$roomId}", self::TTL);
        $pipe->execute();

        return $kinds !== [];
    }

    public function set(string $roomId, string $userId, int $seat, array $kinds): void
    {
        $key = self::PREFIX . "{$roomId}:{$userId}";

        $pipe = Redis::pipeline();
        if ($kinds === []) {
            $pipe->hdel($key, (string) $seat);
        } else {
            $pipe->hset($key, (string) $seat, implode(',', array_map(
                fn (MarkKind $k) => $k->value,
                $kinds,
            )));
        }
        $pipe->expire($key, self::TTL);
        $pipe->incr(self::PREFIX . "rev:{$roomId}:{$userId}");
        $pipe->expire(self::PREFIX . "rev:{$roomId}:{$userId}", self::TTL);
        $pipe->sAdd(self::PREFIX . "rooms:{$roomId}", $userId);
        $pipe->expire(self::PREFIX . "rooms:{$roomId}", self::TTL);
        $pipe->execute();
    }

    public function clear(string $roomId, string $userId, int $seat, ?MarkKind $kind = null): void
    {
        $key = self::PREFIX . "{$roomId}:{$userId}";

        if ($kind === null) {
            // Clear all marks for the seat.
            $pipe = Redis::pipeline();
            $pipe->hdel($key, (string) $seat);
            $pipe->expire($key, self::TTL);
            $pipe->incr(self::PREFIX . "rev:{$roomId}:{$userId}");
            $pipe->expire(self::PREFIX . "rev:{$roomId}:{$userId}", self::TTL);
            $pipe->execute();

            return;
        }

        $current = Redis::hget($key, (string) $seat);
        if ($current === false) {
            return;
        }

        $kinds = explode(',', (string) $current);
        $kinds = array_values(array_filter($kinds, fn (string $k) => $k !== $kind->value));

        $pipe = Redis::pipeline();
        if ($kinds === []) {
            $pipe->hdel($key, (string) $seat);
        } else {
            $pipe->hset($key, (string) $seat, implode(',', $kinds));
        }
        $pipe->expire($key, self::TTL);
        $pipe->incr(self::PREFIX . "rev:{$roomId}:{$userId}");
        $pipe->expire(self::PREFIX . "rev:{$roomId}:{$userId}", self::TTL);
        $pipe->execute();
    }

    public function wipeRoom(string $roomId): void
    {
        $userIds = Redis::sMembers(self::PREFIX . "rooms:{$roomId}");

        $pipe = Redis::pipeline();
        foreach ($userIds as $userId) {
            $pipe->del(self::PREFIX . "{$roomId}:{$userId}");
            $pipe->del(self::PREFIX . "rev:{$roomId}:{$userId}");
        }
        $pipe->del(self::PREFIX . "rooms:{$roomId}");
        $pipe->execute();
    }

    public function marks(string $roomId, string $userId): array
    {
        $key = self::PREFIX . "{$roomId}:{$userId}";
        $all = Redis::hgetall($key);

        $result = [];
        foreach ($all as $seat => $kindsCsv) {
            $kinds = array_filter(
                array_map(fn (string $v) => MarkKind::tryFrom($v), explode(',', (string) $kindsCsv)),
                fn ($k) => $k !== null,
            );

            if ($kinds !== []) {
                $result[(int) $seat] = array_values($kinds);
            }
        }

        return $result;
    }

    public function notesRev(string $roomId, string $userId): int
    {
        return (int) Redis::get(self::PREFIX . "rev:{$roomId}:{$userId}");
    }
}
