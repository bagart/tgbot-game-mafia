<?php

declare(strict_types=1);

namespace Tests\Support;

use BAGArt\TelegramBotMafia\Contracts\ClockContract;

final class FakeClock implements ClockContract
{
    public function __construct(public int $now = 1_700_000_000) {}

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }

    public function now(): int
    {
        return $this->now;
    }
}
