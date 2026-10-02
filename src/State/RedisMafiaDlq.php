<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\MafiaDlqContract;
use Illuminate\Contracts\Cache\Repository;

final readonly class RedisMafiaDlq implements MafiaDlqContract
{
    private const PREFIX = 'mafia:dlq:';
    private const TTL_SECONDS = 14400; // 4 hours

    public function __construct(
        private Repository $cache,
    ) {
    }

    public function push(string $botId, array $entry): void
    {
        $key = self::PREFIX . $botId;
        $queue = $this->cache->get($key, []);
        $queue[] = $entry;
        $this->cache->put($key, $queue, self::TTL_SECONDS);
    }

    public function pop(string $botId, int $limit = 10): array
    {
        $key = self::PREFIX . $botId;
        $queue = $this->cache->get($key, []);
        $popped = array_slice($queue, 0, $limit);
        $this->cache->put($key, array_slice($queue, $limit), self::TTL_SECONDS);

        return $popped;
    }

    public function ack(string $botId, string $callbackData): void
    {
        $key = self::PREFIX . $botId;
        $queue = $this->cache->get($key, []);
        $queue = array_values(array_filter(
            $queue,
            static fn (array $item) => $item['callbackData'] !== $callbackData,
        ));
        $this->cache->put($key, $queue, self::TTL_SECONDS);
    }

    public function pendingCount(string $botId): int
    {
        return count($this->cache->get(self::PREFIX . $botId, []));
    }
}
