<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Core\RoleCatalog;
use BAGArt\TelegramBotMafia\Core\RoleSetBuilder;

it('builds the count-based preset with civilians filling the rest', function () {
    // checkboxes are opt-in: doctor joins only when checked
    $result = (new RoleSetBuilder)->build(8, ['escort', 'doctor']);

    expect($result->ok)->toBeTrue();
    expect($result->roles)->toHaveCount(8);
    $counts = array_count_values($result->roles);
    expect($counts['mafia'] ?? 0)->toBe(2)
        ->and($counts['detective'] ?? 0)->toBe(1)
        ->and($counts['doctor'] ?? 0)->toBe(1)
        ->and($counts['escort'] ?? 0)->toBe(1)
        ->and($counts['civilian'] ?? 0)->toBeGreaterThan(0);
});

it('drops unchecked optional roles', function () {
    $result = (new RoleSetBuilder)->build(9, []); // preset 9 includes escort+maniac

    expect($result->ok)->toBeTrue();
    expect($result->roles)->not->toContain('escort')
        ->and($result->roles)->not->toContain('maniac');
});

it('always keeps mandatory roles even when unchecked', function () {
    $result = (new RoleSetBuilder)->build(7, []);

    expect($result->roles)->toContain('mafia')
        ->and($result->roles)->toContain('detective');
});

it('caps mafia share at floor(N/3)', function () {
    $result = (new RoleSetBuilder)->build(15, [
        'godfather', 'turncoat', 'bodyguard', 'witness', 'journalist',
        'elder', 'bomzh', 'maniac', 'bandit',
    ]);

    $mafia = count(array_filter($result->roles, fn ($r) => in_array($r, RoleCatalog::mafiaTeamIds(), true)));
    expect($mafia)->toBeLessThanOrEqual(intdiv(15, 3));
});
