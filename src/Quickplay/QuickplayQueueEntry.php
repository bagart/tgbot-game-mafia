<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Quickplay;

/**
 * Immutable snapshot of one player in the matchmaking queue.
 */
final readonly class QuickplayQueueEntry
{
    public function __construct(
        public string $userId,
        public string $name,
        /** Unix timestamp when the player joined. */
        public int $joinedAt,
    ) {
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new self(
            userId: $data['userId'],
            name: $data['name'],
            joinedAt: $data['joinedAt'],
        );
    }

    public function toJson(): string
    {
        return json_encode([
            'userId' => $this->userId,
            'name' => $this->name,
            'joinedAt' => $this->joinedAt,
        ], JSON_THROW_ON_ERROR);
    }
}
