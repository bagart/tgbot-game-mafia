<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\GameCoordinator;
use BAGArt\TelegramBotMafia\Tests\Support\CoordinatorFactory;

function coordinator(): GameCoordinator
{
    return CoordinatorFactory::make();
}

it('runs a lobby from creation through a started game', function () {
    $c = coordinator();

    // host creates an interface room (public by design)
    $room = $c->createRoom('interface', null, 'Test Table', 'host1', 'Host', 5, 6, [], 'en');

    expect($room->visibility)->toBe('public')
        ->and($room->kind)->toBe('interface');

    // fill with bots up to the minimum
    for ($i = 0; $i < 4; $i++) {
        $c->addBot($room->id, 'host1');
    }
    expect(count($c->rooms()->activeMembers($room->id)))->toBe(5);

    // humans must confirm the DM channel before the start
    [, $blocked] = $c->start($room->id);
    expect($blocked)->toBe('interface.dm_required');

    $c->confirmDm($room->id, 'host1');
    [$plans, $reason] = $c->start($room->id);
    expect($reason)->toBeNull()
        ->and($plans)->not->toBeEmpty();

    $game = $c->store()->gameByUser('host1');
    expect($game)->not->toBeNull()
        ->and(count($game->seats))->toBe(5)
        ->and($game->phase->value)->toBe('night');

    // bots already acted; every seat holds a role
    foreach ($game->seats as $seat) {
        expect($seat->role)->not->toBeNull();
    }
});

it('counts one active game per user and refuses doubles', function () {
    $c = coordinator();
    $first = $c->createRoom('interface', null, 'T', 'host1', 'Host', 5, 6, [], 'en');
    for ($i = 0; $i < 4; $i++) {
        $c->addBot($first->id, 'host1');
    }
    $c->confirmDm($first->id, 'host1');
    $c->start($first->id);

    $secondRoom = $c->createRoom('interface', null, 'T2', 'rival', 'Rival', 5, 6, [], 'en');
    $result = $c->join($secondRoom->id, 'host1', 'Host');

    expect($result['toast'])->toBe('errors.already_in_other_game');
});
