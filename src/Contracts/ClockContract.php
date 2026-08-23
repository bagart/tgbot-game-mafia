<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Contracts;

/**
 * Time source abstraction: production uses wall clock, tests inject a fake.
 */
interface ClockContract
{
    public function now(): int;
}
