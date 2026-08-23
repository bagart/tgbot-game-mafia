<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\ClockContract;

final class SystemClock implements ClockContract
{
    public function now(): int
    {
        return time();
    }
}
