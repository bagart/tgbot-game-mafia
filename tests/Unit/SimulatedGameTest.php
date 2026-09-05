<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Tests\Support\CoordinatorFactory;

it('completes a headless game to an outcome through deadlines alone', function () {
    $c = CoordinatorFactory::make();
    $room = $c->createRoom('interface', null, 'Headless', 'host1', 'Host', 5, 5, [], 'en');
    for ($i = 0; $i < 4; $i++) {
        $c->addBot($room->id, 'host1');
    }
    $c->confirmDm($room->id, 'host1');
    [, $reason] = $c->start($room->id);
    expect($reason)->toBeNull();
    $gameId = (string) $c->rooms()->requireRoom($room->id)->lastGameId;

    // nobody ever acts: every phase transition must be driven by the
    // lazy deadline enforcement (the mafia:sweep path)
    $ended = false;
    for ($i = 0; $i < 200; $i++) {
        foreach ($c->store()->activeGames() as $game) {
            $c->advanceIfOverdue($game->gameId);
        }
        if ($c->store()->loadSnapshot($gameId)?->phase === PhaseEnum::Ended) {
            $ended = true;

            break;
        }
        CoordinatorFactory::$clock->advance(600);
    }

    expect($ended)->toBeTrue('game stalled without human actions');

    $finished = $c->store()->loadSnapshot($gameId);
    expect($finished?->phase)->toBe(PhaseEnum::Ended)
        ->and($finished?->result)->not->toBeNull()
        ->and($c->store()->activeGames())->toBeEmpty();
});

it('sweep skips paused games and reports active ones only', function () {
    $c = CoordinatorFactory::make();
    $room = $c->createRoom('interface', null, 'Paused', 'host1', 'Host', 5, 5, [], 'en');
    for ($i = 0; $i < 4; $i++) {
        $c->addBot($room->id, 'host1');
    }
    $c->confirmDm($room->id, 'host1');
    $c->start($room->id);

    expect(count($c->store()->activeGames()))->toBe(1);

    $game = $c->store()->gameByUser('host1');
    $c->pause($game->gameId, 'host1');
    CoordinatorFactory::$clock->advance(10_000);
    foreach ($c->store()->activeGames() as $active) {
        $plans = $c->advanceIfOverdue($active->gameId);
        expect($plans)->toBeEmpty();
    }
    expect($c->store()->gameByUser('host1')?->phase)->not->toBe(PhaseEnum::Ended);

    $c->resume($game->gameId, 'host1');
    $c->advanceIfOverdue($game->gameId);
});
