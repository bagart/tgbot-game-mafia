<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Bots;

use BAGArt\TelegramBotMafia\I18n\LangPack;

/**
 * Unique filler-player names: pool from the persona pack + collision suffix.
 * Uniqueness holds within a factory instance (per room creation flow).
 */
final class NicknameFactory
{
    private int $collisionCounter = 0;

    /** @var array<string, true> */
    private array $issued = [];

    private \Closure $random;

    public function __construct(
        private readonly LangPack $pack,
        ?\Closure $random = null,
    ) {
        $this->random = $random ?? static fn (int $max): int => random_int(0, $max);
    }

    public function next(): string
    {
        $pool = $this->pack->namePool();
        if ($pool === []) {
            return 'Bot_'.(++$this->collisionCounter);
        }

        $base = $pool[($this->random)(count($pool) - 1)];
        if (! isset($this->issued[$base])) {
            $this->issued[$base] = true;

            return $base;
        }

        // collision: suffix loop guarantees uniqueness within this room
        do {
            $name = str_replace(
                ['{base}', '{n}'],
                [$base, ++$this->collisionCounter],
                $this->pack->collisionSuffixFormat()
            );
        } while (isset($this->issued[$name]));

        $this->issued[$name] = true;

        return $name;
    }
}
