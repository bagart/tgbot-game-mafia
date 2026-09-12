<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\MafiaMetricsContract;

final class InMemoryMafiaMetrics implements MafiaMetricsContract
{
    /** @var  array<string, list<array{winner: string, duration: int, roles: array}>>  */
    private array $games = [];

    /** @var  array<string, array<string, int>>  */
    private array $counters = [];

    public function recordGameCompleted(string $botId, string $winner, int $durationSeconds, array $roleCounts): void
    {
        $this->games[$botId][] = [
            'winner' => $winner,
            'duration' => $durationSeconds,
            'roles' => $roleCounts,
        ];
    }

    public function increment(string $botId, string $metric, int $value = 1): void
    {
        $this->counters[$botId][$metric] = ($this->counters[$botId][$metric] ?? 0) + $value;
    }

    public function stats(string $botId): array
    {
        $games = $this->games[$botId] ?? [];
        $total = count($games);
        if ($total === 0) {
            return [
                'total_games' => 0,
                'mafia_wins' => 0,
                'town_wins' => 0,
                'solo_wins' => 0,
                'avg_duration_seconds' => 0,
            ];
        }

        $mafiaWins = 0;
        $townWins = 0;
        $soloWins = 0;
        $totalDuration = 0;

        foreach ($games as $game) {
            $totalDuration += $game['duration'];
            match ($game['winner']) {
                'mafia' => $mafiaWins++,
                'town' => $townWins++,
                default => $soloWins++,
            };
        }

        return [
            'total_games' => $total,
            'mafia_wins' => $mafiaWins,
            'town_wins' => $townWins,
            'solo_wins' => $soloWins,
            'avg_duration_seconds' => (int) ($totalDuration / $total),
        ];
    }
}
