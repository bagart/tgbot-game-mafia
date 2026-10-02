<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\ClockContract;
use BAGArt\TelegramBotMafia\Contracts\QuickplayQueueContract;
use BAGArt\TelegramBotMafia\Quickplay\QuickplayQueueEntry;

/**
 * Process-memory quickplay queue: default for tests. Production swaps in
 * a Redis sorted-set implementation behind the same contract.
 */
final class InMemoryQuickplayQueue implements QuickplayQueueContract
{
    /** @var array<string, array<string, QuickplayQueueEntry>> botId => [userId => entry] */
    private array $queues = [];

    public function __construct(
        private readonly ClockContract $clock,
    ) {
    }

    public function join(string $botId, string $userId, string $name): int
    {
        $this->queues[$botId][$userId] = new QuickplayQueueEntry(
            userId: $userId,
            name: $name,
            joinedAt: $this->clock->now(),
        );

        return $this->position($botId, $userId);
    }

    public function cancel(string $botId, string $userId): void
    {
        unset($this->queues[$botId][$userId]);
    }

    public function entries(string $botId): array
    {
        $entries = $this->queues[$botId] ?? [];
        usort($entries, fn (QuickplayQueueEntry $a, QuickplayQueueEntry $b) => $a->joinedAt <=> $b->joinedAt);

        return array_values($entries);
    }

    public function drainExpired(string $botId, int $olderThanSeconds): array
    {
        $cutoff = $this->clock->now() - $olderThanSeconds;
        $drained = [];
        foreach ($this->entries($botId) as $entry) {
            if ($entry->joinedAt < $cutoff) {
                $drained[] = $entry;
                unset($this->queues[$botId][$entry->userId]);
            }
        }

        return $drained;
    }

    public function count(string $botId): int
    {
        return count($this->queues[$botId] ?? []);
    }

    public function contains(string $botId, string $userId): bool
    {
        return isset($this->queues[$botId][$userId]);
    }

    private function position(string $botId, string $userId): int
    {
        $i = 0;
        foreach ($this->entries($botId) as $entry) {
            if ($entry->userId === $userId) {
                return $i;
            }
            $i++;
        }

        return 0;
    }
}
