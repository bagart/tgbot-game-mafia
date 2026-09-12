<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\State\InMemoryMafiaMetrics;

it('records game completion', function () {
    $metrics = new InMemoryMafiaMetrics();

    $metrics->recordGameCompleted('bot1', 'mafia', 120, ['mafia' => 3, 'town' => 7]);

    $stats = $metrics->stats('bot1');
    expect($stats['total_games'])->toBe(1);
    expect($stats['mafia_wins'])->toBe(1);
    expect($stats['town_wins'])->toBe(0);
    expect($stats['solo_wins'])->toBe(0);
    expect($stats['avg_duration_seconds'])->toBe(120);
});

it('aggregates multiple games', function () {
    $metrics = new InMemoryMafiaMetrics();

    $metrics->recordGameCompleted('bot1', 'mafia', 100, []);
    $metrics->recordGameCompleted('bot1', 'town', 200, []);
    $metrics->recordGameCompleted('bot1', 'town', 150, []);
    $metrics->recordGameCompleted('bot1', 'solo', 80, []);

    $stats = $metrics->stats('bot1');
    expect($stats['total_games'])->toBe(4);
    expect($stats['mafia_wins'])->toBe(1);
    expect($stats['town_wins'])->toBe(2);
    expect($stats['solo_wins'])->toBe(1);
    expect($stats['avg_duration_seconds'])->toBe(132); // (100+200+150+80)/4
});

it('increments counters', function () {
    $metrics = new InMemoryMafiaMetrics();

    $metrics->increment('bot1', 'callback.processed');
    $metrics->increment('bot1', 'callback.processed');
    $metrics->increment('bot1', 'callback.failed');

    // Counters are stored but not exposed via stats() — verify via no exception
    expect($metrics->stats('bot1')['total_games'])->toBe(0);
});

it('returns empty stats for unknown bot', function () {
    $metrics = new InMemoryMafiaMetrics();

    $stats = $metrics->stats('unknown_bot');
    expect($stats['total_games'])->toBe(0);
    expect($stats['mafia_wins'])->toBe(0);
    expect($stats['town_wins'])->toBe(0);
    expect($stats['solo_wins'])->toBe(0);
    expect($stats['avg_duration_seconds'])->toBe(0);
});
