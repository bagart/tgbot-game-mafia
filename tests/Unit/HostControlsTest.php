<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Tests\Support\CoordinatorFactory;

function hostGame(): array
{
    $c = CoordinatorFactory::make();
    $room = $c->createRoom('interface', null, 'HostCtl', 'host1', 'Host', 5, 5, [], 'en');
    for ($i = 0; $i < 4; $i++) {
        $c->addBot($room->id, 'host1');
    }
    $c->confirmDm($room->id, 'host1');
    [, $reason] = $c->start($room->id);
    expect($reason)->toBeNull();
    $gameId = (string) $c->rooms()->requireRoom($room->id)->lastGameId;

    return [$c, $room, $gameId];
}

it('survives a pause → restart → resume roundtrip (RUN-6)', function () {
    [$c, , $gameId] = hostGame();
    $before = $c->store()->loadSnapshot($gameId);
    expect($before?->deadlineAt - CoordinatorFactory::$clock->now())->toBe(75);

    // non-host cannot pause
    [, $toast] = $c->pause($gameId, 'intruder');
    expect($toast)->toBe('errors.only_host_action')
        ->and($c->store()->loadSnapshot($gameId)?->pausedAt)->toBeNull();

    $c->pause($gameId, 'host1');

    // process restart over the same stores
    $restarted = CoordinatorFactory::restartFrom($c);
    CoordinatorFactory::$clock->advance(5000);
    $restarted->advanceIfOverdue($gameId);
    expect($restarted->store()->loadSnapshot($gameId)?->phase)->toBe(PhaseEnum::Night)
        ->and($restarted->store()->loadSnapshot($gameId)?->pausedAt)->not->toBeNull();

    // actions are rejected while paused
    [, $voteToast] = $restarted->castVote($gameId, 'host1', null);
    expect($voteToast)->toBe('errors.wrong_phase_toast');

    // resume shifts the deadline by the paused duration
    $pausedFor = CoordinatorFactory::$clock->now() - $restarted->store()->loadSnapshot($gameId)?->pausedAt;
    [$plans] = $restarted->resume($gameId, 'host1');
    $after = $restarted->store()->loadSnapshot($gameId);
    expect($after?->pausedAt)->toBeNull()
        ->and($after?->deadlineAt - CoordinatorFactory::$clock->now())->toBe(75)
        ->and($plans)->not->toBeEmpty();

    // deadline still fires after resume: paused span must not count
    CoordinatorFactory::$clock->advance(76);
    $restarted->advanceIfOverdue($gameId);
    expect($restarted->store()->loadSnapshot($gameId)?->phaseNumber)->toBeGreaterThan($before->phaseNumber);
});

it('extends the phase once per phase and rejects the second press (GRP-8)', function () {
    [$c, , $gameId] = hostGame();
    $snapshot = $c->store()->loadSnapshot($gameId);
    $baseDeadline = $snapshot?->deadlineAt;

    [, $toast] = $c->extendPhase($gameId, 'intruder');
    expect($toast)->toBe('errors.only_host_action')
        ->and($c->store()->loadSnapshot($gameId)?->deadlineAt)->toBe($baseDeadline);

    [, $toast] = $c->extendPhase($gameId, 'host1');
    expect($toast)->toContain('+30')
        ->and($c->store()->loadSnapshot($gameId)?->deadlineAt)->toBe($baseDeadline + 30);

    [, $toast] = $c->extendPhase($gameId, 'host1');
    expect($toast)->toBe('errors.extension_used_toast')
        ->and($c->store()->loadSnapshot($gameId)?->deadlineAt)->toBe($baseDeadline + 30);

    // next phase gets its own extension budget: advance into discussion
    CoordinatorFactory::$clock->advance(200);
    $c->advanceIfOverdue($gameId);
    $discussion = $c->store()->loadSnapshot($gameId);
    expect($discussion?->phase)->toBe(PhaseEnum::DayDiscussion);
    [, $toast] = $c->extendPhase($gameId, 'host1');
    expect($toast)->toContain('+30')
        ->and($c->store()->loadSnapshot($gameId)?->deadlineAt)->toBe(
            $discussion?->deadlineAt + 30
        );
});

it('enforces the emergency assembly budget (GRP-9)', function () {
    $c = CoordinatorFactory::make();
    $room = $c->createRoom('interface', null, 'SOS', 'h1', 'H1', 5, 5, [], 'en');
    for ($i = 0; $i < 2; $i++) {
        $c->addBot($room->id, 'h1');
    }
    $c->join($room->id, 'h2', 'H2');
    $c->join($room->id, 'h3', 'H3');
    foreach (['h1', 'h2', 'h3'] as $h) {
        $c->confirmDm($room->id, $h);
    }
    [, $reason] = $c->start($room->id);
    expect($reason)->toBeNull();
    $gameId = (string) $c->rooms()->requireRoom($room->id)->lastGameId;

    // night is uninterruptible
    [, $toast] = $c->emergencyAssembly($gameId, 'h1');
    expect($toast)->toBe('errors.wrong_phase_toast');

    $toDiscussion = function () use ($c, $gameId) {
        for ($i = 0; $i < 60 && $c->store()->loadSnapshot($gameId)?->phase !== PhaseEnum::DayDiscussion; $i++) {
            CoordinatorFactory::$clock->advance(600);
            $c->advanceIfOverdue($gameId);
        }

        return $c->store()->loadSnapshot($gameId)?->phase;
    };
    $toDiscussion();

    // first call: drops the discussion, voting starts
    [, $toast] = $c->emergencyAssembly($gameId, 'h2');
    expect($toast)->toBeNull()
        ->and($c->store()->loadSnapshot($gameId)?->phase)->toBe(PhaseEnum::DayVoting);

    $craft = function () use ($c, $gameId) {
        $snap = $c->store()->loadSnapshot($gameId);
        $c->store()->saveSnapshot($snap->with(
            phase: PhaseEnum::DayDiscussion,
            votes: [],
            revoteCandidates: [],
            voteRound: 0,
            deadlineAt: CoordinatorFactory::$clock->now() + 60,
        ));
    };

    // per-player budget: the same player cannot call twice…
    $craft();
    [, $toast] = $c->emergencyAssembly($gameId, 'h2');
    expect($toast)->toBe('errors.emergency_used_toast')
        ->and($c->store()->loadSnapshot($gameId)?->emergencyCalls)->toBe(['h2']);

    // …a distinct player still can…
    $craft();
    [, $toast] = $c->emergencyAssembly($gameId, 'h3');
    expect($toast)->toBeNull()
        ->and($c->store()->loadSnapshot($gameId)?->phase)->toBe(PhaseEnum::DayVoting);

    // …but once two players called, the ≤2/game budget is spent
    $craft();
    [, $toast] = $c->emergencyAssembly($gameId, 'h1');
    expect($toast)->toBe('errors.emergency_budget_toast')
        ->and($c->store()->loadSnapshot($gameId)?->emergencyCalls)->toBe(['h2', 'h3']);
});

it('recreates a lobby with identical settings on rematch (GRP-6)', function () {
    [$c, $room, $gameId] = hostGame();
    CoordinatorFactory::$clock->advance(100_000);
    for ($i = 0; $i < 200; $i++) {
        foreach ($c->store()->activeGames() as $game) {
            $c->advanceIfOverdue($game->gameId);
        }
        if ($c->store()->loadSnapshot($gameId)?->phase === PhaseEnum::Ended) {
            break;
        }
        CoordinatorFactory::$clock->advance(600);
    }
    expect($c->store()->loadSnapshot($gameId)?->phase)->toBe(PhaseEnum::Ended);

    $result = $c->rematch($gameId, 'host1');
    expect($result['toast'])->toBe('end.rematch_created')
        ->and($result['roomId'])->toBeString();

    $lobby = $c->rooms()->requireRoom((string) $result['roomId']);
    expect($lobby->hostUserId)->toBe('host1')
        ->and($lobby->minPlayers)->toBe($room->minPlayers)
        ->and($lobby->maxPlayers)->toBe($room->maxPlayers)
        ->and($lobby->nightSeconds)->toBe($room->nightSeconds)
        ->and($lobby->discussionSeconds)->toBe($room->discussionSeconds)
        ->and($lobby->voteSeconds)->toBe($room->voteSeconds)
        ->and($lobby->status)->toBe('lobby');

    // bots regenerate fresh in the new lobby
    for ($i = 0; $i < 4; $i++) {
        $c->addBot($lobby->id, 'host1');
    }
    $c->confirmDm($lobby->id, 'host1');

    // stale rematch press on a live game is refused
    [, $reason] = $c->start($lobby->id);
    expect($reason)->toBeNull();
    $liveId = (string) $c->rooms()->requireRoom($lobby->id)->lastGameId;
    expect($c->rematch($liveId, 'host1')['toast'])->toBe('errors.stale_action_toast');
});
