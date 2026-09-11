<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\MafiaStateStoreContract;
use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\GameSnapshot;
use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed active-game persistence.
 *
 * Key patterns:
 *   mafia:snapshot:{gameId}       — JSON-serialized GameSnapshot (TTL 4h)
 *   mafia:byChat:{chatId}         — gameId (string) for chat→game lookup
 *   mafia:byUser:{userId}         — gameId (set) for user→game lookup
 *   mafia:sayLock:{userId}:{gid}  — 1 (TTL 5min) for say-relay atomicity
 *   mafia:dmConfirm:{roomId}:{uid} — 1 (TTL 1h) for DM delivery tracking
 *
 * All operations are atomic or idempotent. Concurrent callback race
 * conditions are handled by the caller (snapshot revision check).
 */
final class RedisMafiaStateStore implements MafiaStateStoreContract
{
    private const string PREFIX = 'mafia:';
    private const int SNAPSHOT_TTL = 14400; // 4 hours
    private const int SAY_LOCK_TTL = 300; // 5 minutes
    private const int DM_CONFIRM_TTL = 3600; // 1 hour

    public function saveSnapshot(GameSnapshot $snapshot): void
    {
        $gameId = $snapshot->gameId;
        $chatId = $snapshot->chatId;
        $ended = $snapshot->phase === PhaseEnum::Ended;

        $json = $snapshot->toJson();

        $pipe = Redis::pipeline();
        $pipe->set(self::PREFIX . "snapshot:{$gameId}", $json, self::SNAPSHOT_TTL);

        // Update chat index.
        $chatKey = self::PREFIX . "byChat:{$chatId}";
        if ($chatId !== null && ! $ended) {
            $pipe->set($chatKey, $gameId, self::SNAPSHOT_TTL);
        } else {
            $pipe->del($chatKey);
        }

        // Update user index: clear old entries for this game, re-add active seats.
        $existingUsers = $pipe->sMembers(self::PREFIX . "users:{$gameId}");
        $pipe->execute();

        if (! empty($existingUsers)) {
            $pipe = Redis::pipeline();
            foreach ($existingUsers as $uid) {
                $pipe->sRem(self::PREFIX . "byUser:{$uid}", $gameId);
            }
            $pipe->del(self::PREFIX . "users:{$gameId}");
            $pipe->execute();
        }

        if (! $ended) {
            $pipe = Redis::pipeline();
            foreach ($snapshot->seats as $seat) {
                if (! $seat->isBot) {
                    $pipe->sAdd(self::PREFIX . "byUser:{$seat->userId}", $gameId);
                    $pipe->expire(self::PREFIX . "byUser:{$seat->userId}", self::SNAPSHOT_TTL);
                    $pipe->sAdd(self::PREFIX . "users:{$gameId}", $seat->userId);
                }
            }
            $pipe->expire(self::PREFIX . "users:{$gameId}", self::SNAPSHOT_TTL);
            $pipe->execute();
        }
    }

    public function loadSnapshot(string $gameId): ?GameSnapshot
    {
        $json = Redis::get(self::PREFIX . "snapshot:{$gameId}");

        if ($json === null) {
            return null;
        }

        return GameSnapshot::fromJson((string) $json);
    }

    public function deleteSnapshot(string $gameId): void
    {
        $snapshot = $this->loadSnapshot($gameId);

        $pipe = Redis::pipeline();
        $pipe->del(self::PREFIX . "snapshot:{$gameId}");

        if ($snapshot !== null) {
            if ($snapshot->chatId !== null) {
                $pipe->del(self::PREFIX . "byChat:{$snapshot->chatId}");
            }

            $users = Redis::sMembers(self::PREFIX . "users:{$gameId}");
            foreach ($users as $uid) {
                $pipe->sRem(self::PREFIX . "byUser:{$uid}", $gameId);
            }
            $pipe->del(self::PREFIX . "users:{$gameId}");
        }

        $pipe->del(self::PREFIX . "sayLock:{$gameId}");
        $pipe->execute();
    }

    public function gameByChat(string $chatId): ?GameSnapshot
    {
        $gameId = Redis::get(self::PREFIX . "byChat:{$chatId}");

        return $gameId !== null ? $this->loadSnapshot((string) $gameId) : null;
    }

    public function gameByUser(string $userId): ?GameSnapshot
    {
        $gameIds = Redis::sMembers(self::PREFIX . "byUser:{$userId}");

        if ($gameIds === []) {
            return null;
        }

        // Return the first active game (user should only have one per platform rule).
        return $this->loadSnapshot((string) reset($gameIds));
    }

    public function activeGames(): array
    {
        // Scan for all snapshot keys.
        $games = [];
        $cursor = null;

        do {
            [$cursor, $keys] = Redis::scan(100, 'MATCH', self::PREFIX . 'snapshot:*', 'COUNT', 100);
            foreach ($keys as $key) {
                $json = Redis::get($key);
                if ($json === null) {
                    continue;
                }
                $snapshot = GameSnapshot::fromJson((string) $json);
                if ($snapshot->phase !== PhaseEnum::Ended) {
                    $games[] = $snapshot;
                }
            }
        } while ($cursor !== 0 && $cursor !== null && $cursor !== '0');

        return $games;
    }

    public function consumeSayLock(string $userId, string $gameId): bool
    {
        $key = self::PREFIX . "sayLock:{$userId}:{$gameId}";
        $val = Redis::get($key);

        if ($val !== null) {
            Redis::del($key);

            return true;
        }

        return false;
    }

    public function setSayLock(string $userId, string $gameId): void
    {
        Redis::set(
            self::PREFIX . "sayLock:{$userId}:{$gameId}",
            $gameId,
            'EX',
            self::SAY_LOCK_TTL,
        );
    }

    public function dmConfirmations(string $roomId, array $userIds): array
    {
        $out = [];
        foreach ($userIds as $u) {
            $out[$u] = (bool) Redis::get(self::PREFIX . "dmConfirm:{$roomId}:{$u}");
        }

        return $out;
    }

    public function setDmConfirmed(string $roomId, string $userId): void
    {
        Redis::set(
            self::PREFIX . "dmConfirm:{$roomId}:{$userId}",
            '1',
            'EX',
            self::DM_CONFIRM_TTL,
        );
    }
}
