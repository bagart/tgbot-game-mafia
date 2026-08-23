<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\GameSnapshot;
use BAGArt\TelegramBotMafia\Core\SeatState;

function snapshotFixture(): GameSnapshot
{
    return new GameSnapshot(
        gameId: 'g1',
        roomId: 'r1',
        chatId: '-100123',
        locale: 'ru',
        phase: PhaseEnum::Night,
        phaseNumber: 2,
        dayNumber: 1,
        deadlineAt: 42,
        mirrorOn: true,
        seats: [
            new SeatState(seat: 1, userId: 'u1', name: 'Ann', isBot: false, role: 'detective'),
            new SeatState(seat: 2, userId: 'bot:1', name: 'Барон', isBot: true, role: 'mafia'),
            new SeatState(seat: 3, userId: 'u3', name: 'Maria', isBot: false, role: 'civilian', alive: false),
        ],
        votes: ['u1' => 2],
        pausedAt: null,
    );
}

it('roundtrips through versioned JSON losslessly', function () {
    $snapshot = snapshotFixture();

    $restored = GameSnapshot::fromJson($snapshot->toJson());

    expect($restored->gameId)->toBe('g1')
        ->and($restored->phase)->toBe(PhaseEnum::Night)
        ->and($restored->seats)->toHaveCount(3)
        ->and($restored->seats[1]->name)->toBe('Барон')
        ->and($restored->votes)->toBe(['u1' => 2])
        ->and($restored->mirrorOn)->toBeTrue();
});

it('keeps nullable fields nullable through serialization', function () {
    $json = snapshotFixture()->toJson();
    expect(json_decode($json, true)['pausedAt'])->toBeNull();

    $paused = snapshotFixture()->with(pausedAt: 777);
    expect(GameSnapshot::fromJson($paused->toJson())->pausedAt)->toBe(777);
});

it('rejects unknown schema versions', function () {
    $data = json_decode(snapshotFixture()->toJson(), true);
    $data['v'] = 99;

    GameSnapshot::fromJson(json_encode($data));
})->throws(RuntimeException::class);
