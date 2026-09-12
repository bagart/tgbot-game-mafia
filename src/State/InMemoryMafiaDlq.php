<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\MafiaDlqContract;

final class InMemoryMafiaDlq implements MafiaDlqContract
{
    /** @var  array<string, list<array{callbackData: string, botId: string, userId: string, exception: string, failedAt: string}>>  */
    private array $queues = [];

    public function push(string $botId, array $entry): void
    {
        $this->queues[$botId][] = $entry;
    }

    public function pop(string $botId, int $limit = 10): array
    {
        $items = $this->queues[$botId] ?? [];
        $popped = array_slice($items, 0, $limit);
        $this->queues[$botId] = array_slice($items, $limit);

        return $popped;
    }

    public function ack(string $botId, string $callbackData): void
    {
        $this->queues[$botId] = array_values(array_filter(
            $this->queues[$botId] ?? [],
            static fn (array $item) => $item['callbackData'] !== $callbackData,
        ));
    }

    public function pendingCount(string $botId): int
    {
        return count($this->queues[$botId] ?? []);
    }
}
