<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Config\MafiaDefaults;
use BAGArt\TelegramBotMafia\Discipline\FreezePolicy;
use BAGArt\TelegramBotMafia\Rooms\JoinGuard;
use BAGArt\TelegramBotMafia\Rooms\Room;
use BAGArt\TelegramBotMafia\State\InMemoryMafiaStateStore;
use BAGArt\TelegramBotMafia\State\InMemoryProfileStore;
use BAGArt\TelegramBotMafia\Tests\Support\FakeClock;

function guardFixtures(): array
{
    $clock = new FakeClock;
    $store = new InMemoryMafiaStateStore;
    $profiles = new InMemoryProfileStore;

    $room = new Room(
        id: 'r1', kind: 'interface', visibility: 'public', status: 'lobby',
        title: 'T', hostUserId: 'host', chatId: null,
        minPlayers: 5, maxPlayers: 6, checkedRoles: [], locale: 'en', createdAt: 0,
    );

    return [$room, new JoinGuard($store, $profiles, $clock), $store, $profiles, $clock];
}

it('rejects joining a started room, full room, and frozen users', function () {
    [$room, $guard] = guardFixtures();

    expect($guard->check($room, 3, 'u9'))->toBeNull();

    expect($guard->check($room->with(status: 'running'), 3, 'u9'))
        ->toBe('rooms.join_started_toast');
    expect($guard->check($room, 6, 'u9'))->toBe('rooms.join_full_toast');
});

it('freezes after two consecutive skips and releases after the timeout', function () {
    $clock = new FakeClock;
    $profiles = new InMemoryProfileStore;
    $store = new InMemoryMafiaStateStore;
    $policy = new FreezePolicy($profiles, $clock);
    $room = new Room(
        id: 'r1', kind: 'interface', visibility: 'public', status: 'lobby',
        title: 'T', hostUserId: 'host', chatId: null,
        minPlayers: 5, maxPlayers: 6, checkedRoles: [], locale: 'en', createdAt: 0,
    );
    $guard = new JoinGuard($store, $profiles, $clock);

    expect($policy->registerSkip('ghost'))->toBeNull();
    expect($policy->registerSkip('ghost'))->not->toBeNull();
    expect($policy->isFrozen('ghost'))->toBeTrue();

    // frozen users cannot pass the guard
    expect($guard->check($room, 3, 'ghost'))->toBe('rooms.join_frozen_toast');

    $clock->advance(MafiaDefaults::FREEZE_MINUTES * 60 + 1);
    expect($policy->isFrozen('ghost'))->toBeFalse()
        ->and($guard->check($room, 3, 'ghost'))->toBeNull();
});

it('resets the skip counter on full participation', function () {
    [, , , $profiles, $clock] = guardFixtures();
    $policy = new FreezePolicy($profiles, $clock);

    $policy->registerSkip('player');
    $policy->registerParticipation('player');

    expect($profiles->skips('player'))->toBe(0)
        ->and($policy->registerSkip('player'))->toBeNull();
});
