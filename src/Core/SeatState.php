<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core;

/**
 * One seated player's state inside a game. Pure data (Redis-safe readonly
 * DTO); transient night flags reset at dawn by the resolver.
 */
final readonly class SeatState
{
    public function __construct(
        public int $seat,
        public string $userId,
        public string $name,
        public bool $isBot,
        public ?string $role,
        public bool $alive = true,
        /** sniper / lone bandit bullets left */
        public int $bullets = 0,
        /** doctor self-heals remaining */
        public int $selfHealLeft = 0,
        /** elder: first night kill attempt survives */
        public bool $elderShield = false,
        /** missed the last public vote while alive */
        public bool $missedVote = false,
        // transient night flags
        public bool $tonightBlocked = false,
        public bool $tonightProtected = false,
        public bool $tonightHealed = false,
    ) {}

    public function with(...$props): self
    {
        return new self(
            seat: $props['seat'] ?? $this->seat,
            userId: $props['userId'] ?? $this->userId,
            name: $props['name'] ?? $this->name,
            isBot: $props['isBot'] ?? $this->isBot,
            role: $props['role'] ?? $this->role,
            alive: $props['alive'] ?? $this->alive,
            bullets: $props['bullets'] ?? $this->bullets,
            selfHealLeft: $props['selfHealLeft'] ?? $this->selfHealLeft,
            elderShield: $props['elderShield'] ?? $this->elderShield,
            missedVote: $props['missedVote'] ?? $this->missedVote,
            tonightBlocked: $props['tonightBlocked'] ?? $this->tonightBlocked,
            tonightProtected: $props['tonightProtected'] ?? $this->tonightProtected,
            tonightHealed: $props['tonightHealed'] ?? $this->tonightHealed,
        );
    }
}
