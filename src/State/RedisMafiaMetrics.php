<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\MafiaMetricsContract;
use Illuminate\Contracts\Cache\Repository;

final readonly class RedisMafiaMetrics implements MafiaMetricsContract
{
    private const PREFIX = 'mafia:metrics:';
    private const STATS_PREFIX = 'mafia:stats:';
    private const TTL_SECONDS = 2592000; // 30 days

    public function __construct(
        private Repository $cache,
    ) {}

    public function recordGameCompleted(string $botId, string $winner, int $durationSeconds, array $roleCounts): void
    {
        $this->increment($botId, 'total_games');
        $this->increment($botId, "winner.{$winner}");
        $this->increment($botId, 'total_duration', $durationSeconds);

        foreach ($roleCounts as $role => $count) {
            $this->increment($botId, "roles.{$role}", $count);
        }
    }

    public function increment(string $botId, string $metric, int $value = 1): void
    {
        $key = self::PREFIX . $botId . ':' . $metric;
        $current = $this->cache->get($key, 0);
        $this->cache->put($key, $current + $value, self::TTL_SECONDS);
    }

    public function stats(string $botId): array
    {
        $totalGames = $this->cache->get(self::PREFIX . $botId . ':total_games', 0);
        if ($totalGames === 0) {
            return [
                'total_games' => 0,
                'mafia_wins' => 0,
                'town_wins' => 0,
                'solo_wins' => 0,
                'avg_duration_seconds' => 0,
            ];
        }

        $totalDuration = $this->cache->get(self::PREFIX . $botId . ':total_duration', 0);

        return [
            'total_games' => $totalGames,
            'mafia_wins' => $this->cache->get(self::PREFIX . $botId . ':winner.mafia', 0),
            'town_wins' => $this->cache->get(self::PREFIX . $botId . ':winner.town', 0),
            'solo_wins' => $this->cache->get(self::PREFIX . $botId . ':winner.solo', 0),
            'avg_duration_seconds' => $totalGames > 0 ? (int) ($totalDuration / $totalGames) : 0,
        ];
    }
}
