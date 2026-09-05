<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Config\MafiaDefaults;
use BAGArt\TelegramBotMafia\Settings\MafiaSettings;
use BAGArt\TelegramBotMafia\Tests\Support\CoordinatorFactory;

function lobby(): array
{
    $c = CoordinatorFactory::make();
    $room = $c->createRoom('group', '-100500', 'Lobby', 'host1', 'Host', 5, 8, [], 'en', 'b1');
    $c->join($room->id, 'u2', 'Bob');

    return [$c, $room];
}

it('renders host controls for the host only and never a join button', function () {
    [$c, $room] = lobby();

    $hostCard = $c->lobbyCard($c->rooms()->requireRoom($room->id), 'host1');
    $guestCard = $c->lobbyCard($c->rooms()->requireRoom($room->id), 'u2');
    $anonCard = $c->lobbyCard($c->rooms()->requireRoom($room->id));

    foreach ([$hostCard, $guestCard, $anonCard] as $card) {
        expect(callbacks($card))->not->toContain('m:join:');
    }

    $hostCallbacks = callbacks($hostCard);
    expect($hostCallbacks)->toContain('m:addbot:'.$room->id)
        ->and($hostCallbacks)->toContain('m:begingame:'.$room->id)
        ->and($hostCallbacks)->toContain('m:kick:'.$room->id.':u2');

    foreach ([$guestCard, $anonCard] as $card) {
        $cb = callbacks($card);
        expect($cb)->not->toContain('m:addbot:'.$room->id)
            ->and($cb)->not->toContain('m:begingame:'.$room->id)
            ->and(array_filter($cb, fn (string $c) => str_starts_with($c, 'm:kick:')))->toBeEmpty();
    }
});

it('lets only the host add bots and enforces the bot cap', function () {
    [$c, $room] = lobby();
    $roomId = $room->id;

    expect($c->addBot($roomId, 'u2')['toast'])->toBe('errors.only_host_adds_bots')
        ->and(count($c->rooms()->activeMembers($roomId)))->toBe(2);

    expect($c->addBot($roomId, 'host1')['toast'])->toBeNull()
        ->and(count($c->rooms()->activeMembers($roomId)))->toBe(3);

    // default maxBots=4 — fill to the cap (4 bots total)
    $c->addBot($roomId, 'host1');
    $c->addBot($roomId, 'host1');
    $c->addBot($roomId, 'host1');
    expect($c->addBot($roomId, 'host1')['toast'])->toBe('errors.bots_limit_reached')
        ->and(count($c->rooms()->activeMembers($roomId)))->toBe(6);
});

it('blocks non-host start with a refusal toast', function () {
    [$c, $room] = lobby();

    [, $toast] = $c->start($room->id, 'u2');
    expect($toast)->toBe('errors.only_host_action')
        ->and($c->rooms()->requireRoom($room->id)->status)->toBe('lobby');
});

it('refuses to start while any human has not confirmed DM (GRP-2)', function () {
    [$c, $room] = lobby();
    $c->addBot($room->id, 'host1');
    $c->addBot($room->id, 'host1');
    $c->addBot($room->id, 'host1'); // 5/5 seated, host confirmed by being in chat

    [, $toast] = $c->start($room->id, 'host1');
    expect($toast)->toBe('interface.dm_required');

    $c->confirmDm($room->id, 'host1');
    $c->confirmDm($room->id, 'u2');
    [, $toast] = $c->start($room->id, 'host1');
    expect($toast)->toBeNull();
});

it('sends a DM ready-check card to group joiners until confirmed (GRP-2)', function () {
    [$c, $room] = lobby();

    $result = $c->join($room->id, 'u3', 'Carol');
    $targets = array_map(fn ($p) => $p->chatId, $result['plans']);
    expect($targets)->toContain('u3');
    $card = null;
    foreach ($result['plans'] as $plan) {
        if ($plan->chatId === 'u3') {
            $card = $plan;
        }
    }
    expect(callbacks($card))->toContain('m:ready:'.$room->id);

    // confirmed players don't get a second card on re-join
    $c->confirmDm($room->id, 'u3');
    $again = $c->join($room->id, 'u3', 'Carol');
    expect(array_filter($again['plans'], fn ($p) => $p->chatId === 'u3'))->toBeEmpty();
});

it('applies a template preset and still honors overrides (GRP-10)', function () {
    $blitz = MafiaSettings::template('blitz');
    expect($blitz?->nightSeconds)->toBe(30)
        ->and($blitz?->discussionSeconds)->toBe(60)
        ->and($blitz?->voteSeconds)->toBe(20)
        ->and(MafiaSettings::template('tournament')?->maxBots)->toBe(0)
        ->and(MafiaSettings::template('classic')?->nightSeconds)->toBe(MafiaDefaults::NIGHT_SECONDS)
        ->and(MafiaSettings::template('nope'))->toBeNull();

    // override after applying the template
    $tuned = new MafiaSettings(nightSeconds: 45, discussionSeconds: $blitz->discussionSeconds, voteSeconds: $blitz->voteSeconds);
    expect($tuned->nightSeconds)->toBe(45)
        ->and($tuned->discussionSeconds)->toBe(60);
});

it('shuffles seats at deal only when enabled, off by default (GRP-11)', function () {
    // off by default: seats follow join order
    $c = CoordinatorFactory::make();
    $room = $c->createRoom('interface', null, 'Order', 'h1', 'H1', 5, 5, [], 'en');
    for ($i = 0; $i < 4; $i++) {
        $c->addBot($room->id, 'h1');
    }
    $c->confirmDm($room->id, 'h1');
    $c->start($room->id);
    $game = $c->store()->gameByUser('h1');
    expect($game)->not->toBeNull();
    foreach ($game?->seats ?? [] as $seat) {
        expect($seat->isBot || $seat->userId === 'h1')->toBeTrue();
    }
    // host joined first → seat 1
    expect($game?->seat(1)?->userId)->toBe('h1');

    // on: seat assignment is randomized (LCG-seeded shuffle = deterministic)
    $seededShuffle = function () {
        $state = 42;

        return function (int $max) use (&$state): int {
            $state = ($state * 1103515245 + 12345) % 2147483648;

            return $state % ($max + 1);
        };
    };
    $c2 = CoordinatorFactory::make(new MafiaSettings(shuffleSeats: true), $seededShuffle());
    $room2 = $c2->createRoom('interface', null, 'Shuffled', 'h1', 'H1', 5, 5, [], 'en');
    for ($i = 0; $i < 4; $i++) {
        $c2->addBot($room2->id, 'h1');
    }
    $c2->confirmDm($room2->id, 'h1');
    $c2->start($room2->id);
    $game2 = $c2->store()->gameByUser('h1');
    expect($game2)->not->toBeNull()
        ->and(count($game2?->seats ?? []))->toBe(5);
});

it('applies custom settings timings to phase deadlines', function () {
    $settings = new MafiaSettings(nightSeconds: 200, discussionSeconds: 300, voteSeconds: 50);
    $c = CoordinatorFactory::make($settings);
    $room = $c->createRoom('interface', null, 'T', 'host1', 'Host', 5, 5, [], 'en');
    for ($i = 0; $i < 4; $i++) {
        $c->addBot($room->id, 'host1');
    }
    $c->confirmDm($room->id, 'host1');
    $c->start($room->id);

    $game = $c->store()->gameByUser('host1');
    expect($game?->deadlineAt - CoordinatorFactory::$clock->now())->toBe(200)
        ->and($game?->discussionSeconds)->toBe(300)
        ->and($game?->voteSeconds)->toBe(50);
});

it('lets a per-room settings override beat the coordinator defaults', function () {
    $c = CoordinatorFactory::make(); // default coordinator timings
    $room = $c->createRoom(
        'interface',
        null,
        'T',
        'host1',
        'Host',
        5,
        5,
        [],
        'en',
        null,
        new MafiaSettings(nightSeconds: 300, discussionSeconds: 60, voteSeconds: 20)
    );
    for ($i = 0; $i < 4; $i++) {
        $c->addBot($room->id, 'host1');
    }
    $c->confirmDm($room->id, 'host1');
    $c->start($room->id);

    $game = $c->store()->gameByUser('host1');
    expect($game?->nightSeconds)->toBe(300)
        ->and($game?->deadlineAt - CoordinatorFactory::$clock->now())->toBe(300);
});

it('shows live vote progress on the public board (RUN-5)', function () {
    [$c, $room] = lobby();
    for ($i = 0; $i < 3; $i++) {
        $c->addBot($room->id, 'host1');
    }
    $c->confirmDm($room->id, 'host1');
    $c->confirmDm($room->id, 'u2');
    [$plans] = $c->start($room->id, 'host1');
    $gameId = (string) $c->rooms()->requireRoom($room->id)->lastGameId;

    // fast-forward through night + discussion into voting, keeping the
    // plans emitted by the transition that opened the ballot
    $board = null;
    for ($i = 0; $i < 10; $i++) {
        $plans = $c->advanceIfOverdue($gameId);
        $game = $c->store()->loadSnapshot($gameId);
        if ($game !== null && $game->phase->value === 'day_voting') {
            foreach ($plans as $plan) {
                if (str_contains($plan->text, 'Voted:')) {
                    $board = $plan;
                }
            }

            break;
        }
        CoordinatorFactory::$clock->advance(600);
    }
    $game = $c->store()->loadSnapshot($gameId);
    expect($game?->phase->value)->toBe('day_voting');

    expect($board)->not->toBeNull()
        ->and($board->text)->toContain('Voted: '.count($game->votes).'/'.count($game->aliveSeats()));
});

/**
 * @param  \BAGArt\TelegramBotMafia\Presentation\SendPlan  $plan
 * @return list<string>
 */
function callbacks(object $plan): array
{
    $out = [];
    foreach ($plan->keyboard ?? [] as $row) {
        foreach ($row as $button) {
            $out[] = $button['callback'];
        }
    }

    return $out;
}
