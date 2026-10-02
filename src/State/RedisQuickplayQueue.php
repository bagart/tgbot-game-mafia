<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\ClockContract;
use BAGArt\TelegramBotMafia\Contracts\QuickplayQueueContract;
use BAGArt\TelegramBotMafia\Quickplay\QuickplayQueueEntry;
use Illuminate\Support\Facades\Redis;

/**
 * Redis sorted-set quickplay queue.
 *
 * Key pattern: mafia:qp:{botId} — sorted set, score = joinedAt timestamp, member = userId
 *             mafia:qp:meta:{botId}:{userId} — JSON QuickplayQueueEntry (TTL 2h)
 */
final class RedisQuickplayQueue implements QuickplayQueueContract
{
    private const string PREFIX = 'mafia:qp:';
    private const int META_TTL = 7200; // 2 hours

    public function __construct(
        private readonly ClockContract $clock,
    ) {
    }

    public function join(string $botId, string $userId, string $name): int
    {
        $now = $this->clock->now();
        $metaKey = self::PREFIX . "meta:{$botId}:{$userId}";
        $queueKey = self::PREFIX . $botId;

        $entry = new QuickplayQueueEntry(
            userId: $userId,
            name: $name,
            joinedAt: $now,
        );

        $pipe = Redis::pipeline();
        $pipe->zAdd($queueKey, ['score' => (float) $now, 'value' => $userId]);
        $pipe->expire($queueKey, self::META_TTL);
        $pipe->set($metaKey, json_encode($entry, JSON_THROW_ON_ERROR), 'EX', self::META_TTL);
        $pipe->execute();

        return $this->position($botId, $userId);
    }

    public function cancel(string $botId, string $userId): void
    {
        $pipe = Redis::pipeline();
        $pipe->zRem(self::PREFIX . $botId, $userId);
        $pipe->del(self::PREFIX . "meta:{$botId}:{$userId}");
        $pipe->execute();
    }

    public function entries(string $botId): array
    {
        $userIds = Redis::zRange(self::PREFIX . $botId, 0, -1);
        $entries = [];
        foreach ($userIds as $userId) {
            $json = Redis::get(self::PREFIX . "meta:{$botId}:{$userId}");
            if ($json !== null) {
                $entries[] = QuickplayQueueEntry::fromJson($json);
            }
        }

        return $entries;
    }

    public function drainExpired(string $botId, int $olderThanSeconds): array
    {
        $cutoff = $this->clock->now() - $olderThanSeconds;
        $expired = Redis::zRangeByScore(self::PREFIX . $botId, '-inf', (string) ($cutoff - 1));
        $drained = [];
        foreach ($expired as $userId) {
            $json = Redis::get(self::PREFIX . "meta:{$botId}:{$userId}");
            if ($json !== null) {
                $drained[] = QuickplayQueueEntry::fromJson($json);
            }
        }

        $pipe = Redis::pipeline();
        $pipe->zRemRangeByScore(self::PREFIX . $botId, '-inf', (string) ($cutoff - 1));
        foreach ($expired as $userId) {
            $pipe->del(self::PREFIX . "meta:{$botId}:{$userId}");
        }
        $pipe->execute();

        return $drained;
    }

    public function count(string $botId): int
    {
        return (int) Redis::zCard(self::PREFIX . $botId);
    }

    public function contains(string $botId, string $userId): bool
    {
        return Redis::zScore(self::PREFIX . $botId, $userId) !== false;
    }

    private function position(string $botId, string $userId): int
    {
        $rank = Redis::zRank(self::PREFIX . $botId, $userId);

        return $rank !== false ? (int) $rank : 0;
    }
}
