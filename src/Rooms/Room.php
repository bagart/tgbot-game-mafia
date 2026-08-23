<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Rooms;

/**
 * Lobby entity independent of any chat. Lives until finished; games reference
 * it by id.
 */
final readonly class Room
{
    public function __construct(
        public string $id,
        public string $kind,          // group | interface | mixed
        public string $visibility,    // private | public
        public string $status,        // lobby | running | finished | cancelled
        public string $title,
        public string $hostUserId,
        public ?string $chatId,
        public int $minPlayers,
        public int $maxPlayers,
        /** @var list<string> checked optional role ids */
        public array $checkedRoles,
        public string $locale,
        public ?string $lastGameId = null,
        public int $createdAt = 0,
    ) {}

    public function with(...$props): self
    {
        return new self(
            id: $props['id'] ?? $this->id,
            kind: $props['kind'] ?? $this->kind,
            visibility: $props['visibility'] ?? $this->visibility,
            status: $props['status'] ?? $this->status,
            title: $props['title'] ?? $this->title,
            hostUserId: $props['hostUserId'] ?? $this->hostUserId,
            chatId: array_key_exists('chatId', $props) ? $props['chatId'] : $this->chatId,
            minPlayers: $props['minPlayers'] ?? $this->minPlayers,
            maxPlayers: $props['maxPlayers'] ?? $this->maxPlayers,
            checkedRoles: $props['checkedRoles'] ?? $this->checkedRoles,
            locale: $props['locale'] ?? $this->locale,
            lastGameId: array_key_exists('lastGameId', $props) ? $props['lastGameId'] : $this->lastGameId,
            createdAt: $props['createdAt'] ?? $this->createdAt,
        );
    }
}

/** Room member (human or filler bot). */
final readonly class Member
{
    public const STATE_JOINED = 'joined';

    public const STATE_LEFT = 'left';

    public const STATE_KICKED = 'kicked';

    public const STATE_REPLACED = 'replaced';

    public function __construct(
        public string $userId,
        public string $name,
        public bool $isBot = false,
        public string $state = self::STATE_JOINED,
    ) {}

    public function withState(string $state): self
    {
        return new self($this->userId, $this->name, $this->isBot, $state);
    }
}
