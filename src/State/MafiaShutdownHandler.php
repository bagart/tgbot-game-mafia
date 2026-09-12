<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

/**
 * Graceful shutdown coordinator for the mafia module.
 * Sets a flag on SIGTERM to prevent new game phases from starting.
 * Existing games complete naturally (max 15min for longest phase).
 */
final class MafiaShutdownHandler
{
    private bool $stopping = false;

    public function isStopping(): bool
    {
        return $this->stopping;
    }

    public function prepareShutdown(): void
    {
        $this->stopping = true;
    }

    public function register(): void
    {
        if (PHP_INT_SIZE === 4) {
            return; // Not on 32-bit
        }

        pcntl_signal(SIGTERM, function (): void {
            $this->prepareShutdown();
        });

        pcntl_signal(SIGINT, function (): void {
            $this->prepareShutdown();
        });
    }
}
