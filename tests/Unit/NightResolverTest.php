<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\GameSnapshot;
use BAGArt\TelegramBotMafia\Core\NightAction;
use BAGArt\TelegramBotMafia\Core\NightResolver;
use BAGArt\TelegramBotMafia\Core\SeatState;

function seat(int $n, string $role, bool $alive = true, array $overrides = []): SeatState
{
    $base = new SeatState(
        seat: $n,
        userId: "u{$n}",
        name: "Player{$n}",
        isBot: false,
        role: $role,
        alive: $alive,
        selfHealLeft: $role === 'doctor' ? 1 : 0,
        elderShield: $role === 'elder',
        bullets: in_array($role, ['sniper', 'bandit'], true) ? 1 : 0,
    );

    return $overrides === [] ? $base : $base->with(...$overrides);
}

function nightGame(array $seats): GameSnapshot
{
    static $i = 0;

    return new GameSnapshot(
        gameId: 'ng'.(++$i),
        roomId: 'r',
        chatId: null,
        locale: 'en',
        phase: PhaseEnum::Night,
        phaseNumber: 1,
        dayNumber: 1,
        deadlineAt: 100,
        mirrorOn: false,
        seats: $seats,
    );
}

it('saves the doctor-healed victim and reports the save', function () {
    $snapshot = nightGame([
        seat(1, 'mafia'), seat(2, 'doctor'), seat(3, 'civilian'),
    ]);
    $snapshot = $snapshot->with(nightActions: [
        new NightAction(1, 'kill', 3),
        new NightAction(2, 'heal', 3),
    ]);

    $report = (new NightResolver)->resolve($snapshot);

    expect($report->deaths)->toBe([])
        ->and($report->savedSeat)->toBe(3);
});

it('lets the witness name a killer on an unanswered kill', function () {
    $snapshot = nightGame([
        seat(1, 'mafia'), seat(2, 'civilian'), seat(3, 'witness'),
    ]);
    $snapshot = $snapshot->with(nightActions: [
        new NightAction(1, 'kill', 2),
        new NightAction(3, NightAction::SKIP, null),
    ]);

    $report = (new NightResolver)->resolve($snapshot);

    expect($report->deaths)->toBe([2])
        ->and($report->witnessSeesName)->toBe('Player1');
});

it('blocks the escort-target action for one night only', function () {
    $snapshot = nightGame([
        seat(1, 'escort'), seat(2, 'detective'), seat(3, 'mafia'), seat(4, 'civilian'),
    ]);
    $snapshot = $snapshot->with(nightActions: [
        new NightAction(1, 'block_action', 2),
        new NightAction(3, 'kill', 4),
        // detective (seat 2) submitted nothing -> blocked or absent both skip
    ]);

    $report = (new NightResolver)->resolve($snapshot);

    expect($report->deaths)->toBe([4])
        ->and($report->checkResults)->toBeEmpty();
});

it('cannot kill the bomzh at night', function () {
    $snapshot = nightGame([
        seat(1, 'mafia'), seat(2, 'bomzh'), seat(3, 'civilian'),
    ]);
    $snapshot = $snapshot->with(nightActions: [new NightAction(1, 'kill', 2)]);

    expect((new NightResolver)->resolve($snapshot)->deaths)->toBe([]);
});

it('consumes the elder shield on first attempt', function () {
    $snapshot = nightGame([
        seat(1, 'mafia'), seat(2, 'elder'), seat(3, 'civilian'),
    ]);
    $snapshot = $snapshot->with(nightActions: [new NightAction(1, 'kill', 2)]);

    $report = (new NightResolver)->resolve($snapshot);

    expect($report->deaths)->toBe([])
        ->and($report->elderSaved)->toBe([2]);
});

it('makes the bodyguard die instead of the protected target', function () {
    $snapshot = nightGame([
        seat(1, 'bodyguard'), seat(2, 'detective'), seat(3, 'mafia'),
    ]);
    $snapshot = $snapshot->with(nightActions: [
        new NightAction(1, 'protect', 2),
        new NightAction(3, 'kill', 2),
    ]);

    $report = (new NightResolver)->resolve($snapshot);

    expect($report->deaths)->toBe([1]);
});

it('flags the satanist win when mafia performs the sacrifice', function () {
    $snapshot = nightGame([
        seat(1, 'mafia'), seat(2, 'satanist'), seat(3, 'civilian'), seat(4, 'civilian'),
    ]);
    $snapshot = $snapshot->with(nightActions: [new NightAction(1, 'kill', 2)]);

    $report = (new NightResolver)->resolve($snapshot);

    expect($report->deaths)->toBe([2])
        ->and($report->satanistSacrificed)->toBeTrue();
});
