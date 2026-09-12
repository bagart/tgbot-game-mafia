<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Contracts;

/**
 * Dead-letter queue for failed callback processing.
 * Each item holds enough context to retry the callback later.
 */
interface MafiaDlqContract
{
    /**
     * Push a failed callback for later retry.
     *
     * @param  array{
     *      callbackData: string,
     *      botId: string,
     *      userId: string,
     *      exception: string,
     *      failedAt: string,
     *  }  $entry
     */
    public function push(string $botId, array $entry): void;

    /**
     * Pop up to $limit entries for retry processing.
     *
     * @return  list<array{callbackData: string, botId: string, userId: string, exception: string, failedAt: string}>
     */
    public function pop(string $botId, int $limit = 10): array;

    /** Discard a successfully retried entry. */
    public function ack(string $botId, string $callbackData): void;

    /** Number of pending entries for a bot. */
    public function pendingCount(string $botId): int;
}
