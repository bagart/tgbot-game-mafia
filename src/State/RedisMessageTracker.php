<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\MessageTrackerContract;
use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed message tracker for live Telegram UI editing.
 *
 * Key pattern: mafia:messages:{gameId} → Hash of "{phase}:{chatId}" → messageId
 * TTL: 4 hours (same as game snapshot).
 */
final class RedisMessageTracker implements MessageTrackerContract
{
    private const string PREFIX = 'mafia:messages:';
    private const int TTL = 14400; // 4 hours

    public function track(string $gameId, string $phase, string $chatId, int $messageId): void
    {
        $key = self::PREFIX . $gameId;
        $hashKey = "{$phase}:{$chatId}";

        $pipe = Redis::pipeline();
        $pipe->hset($key, $hashKey, $messageId);
        $pipe->expire($key, self::TTL);
        $pipe->execute();
    }

    public function lastMessage(string $gameId, string $phase, string $chatId): ?int
    {
        $key = self::PREFIX . $gameId;
        $hashKey = "{$phase}:{$chatId}";
        $value = Redis::hget($key, $hashKey);

        return $value !== false ? (int) $value : null;
    }

    public function clear(string $gameId): void
    {
        Redis::del(self::PREFIX . $gameId);
    }
}
