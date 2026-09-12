<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Contracts;

/**
 * Game metrics collector for observability.
 * Tracks completion rates, duration, role win rates.
 */
interface MafiaMetricsContract
{
    /** Record a completed game. */
    public function recordGameCompleted(string $botId, string $winner, int $durationSeconds, array $roleCounts): void;

    /** Increment a named counter. */
    public function increment(string $botId, string $metric, int $value = 1): void;

    /**
     * Get aggregated stats for a bot.
     *
     * @return  array{
     *      total_games: int,
     *      mafia_wins: int,
     *      town_wins: int,
     *      solo_wins: int,
     *      avg_duration_seconds: int,
     *  }
     */
    public function stats(string $botId): array;
}
