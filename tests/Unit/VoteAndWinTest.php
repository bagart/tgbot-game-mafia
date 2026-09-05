<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Core\Enums\GameResultEnum;
use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\GameSnapshot;
use BAGArt\TelegramBotMafia\Core\SeatState;
use BAGArt\TelegramBotMafia\Core\VoteTally;
use BAGArt\TelegramBotMafia\Core\WinConditionChecker;

function voteGame(array $seats, array $votes, array $revote = [], int $round = 0): GameSnapshot
{
    return new GameSnapshot(
        gameId: 'v'.uniqid(),
        roomId: 'r',
        chatId: null,
        locale: 'en',
        phase: PhaseEnum::DayVoting,
        phaseNumber: 1,
        dayNumber: 1,
        deadlineAt: 100,
        mirrorOn: false,
        seats: $seats,
        votes: $votes,
        revoteCandidates: $revote,
        voteRound: $round,
    );
}it('eliminates the strict majority target', function () {
    $game = voteGame(
        [vseat(1), vseat(2), vseat(3)],
        ['a' => 2, 'b' => 2, 'c' => 1],
    );

    $outcome = VoteTally::tally($game);

    expect($outcome->eliminatedSeat)->toBe(2);
});

it('schedules a revote on tie and gives up on the repeated same tie', function () {
    $first = VoteTally::tally(voteGame(
        [vseat(1), vseat(2), vseat(3), vseat(4)],
        ['a' => 1, 'b' => 1, 'c' => 3, 'd' => 3],
    ));
    expect($first->requiresRevote())->toBeTrue()
        ->and($first->tieCandidates)->toBe([1, 3]);

    // same tie again in the revote round -> nobody leaves
    $second = VoteTally::tally(voteGame(
        [vseat(1), vseat(2), vseat(3), vseat(4)],
        ['a' => 1, 'b' => 3, 'c' => 1, 'd' => 3],
        revote: [1, 3],
        round: 1,
    ));
    expect($second->requiresRevote())->toBeFalse()
        ->and($second->eliminatedSeat)->toBeNull();
});

it('counts abstains but they never win', function () {
    $game = voteGame([vseat(1), vseat(2)], ['a' => -1, 'b' => -1]);

    $outcome = VoteTally::tally($game);

    expect($outcome->eliminatedSeat)->toBeNull()
        ->and($outcome->requiresRevote())->toBeFalse();
});

function vseat(int $n): SeatState
{
    return new SeatState(seat: $n, userId: chr(96 + $n), name: 'P'.$n, isBot: false, role: 'civilian');
}

function resultGame(array $roles, array $aliveFlags): GameSnapshot
{
    $seats = [];
    foreach ($roles as $i => $role) {
        $seats[] = new SeatState(
            seat: $i + 1,
            userId: 'u'.($i + 1),
            name: 'P'.($i + 1),
            isBot: false,
            role: $role,
            alive: $aliveFlags[$i],
        );
    }

    return new GameSnapshot('w1', 'r', null, 'en', PhaseEnum::DayDiscussion, 1, 1, 0, false, $seats);
}

it('declares mafia parity win', function () {
    $game = resultGame(
        ['mafia', 'mafia', 'civilian', 'detective'],
        [true, true, false, true],
    );

    expect((new WinConditionChecker())->evaluate($game))->toBe(GameResultEnum::MafiaWon);
});

it('declares town win when every killer is dead', function () {
    $game = resultGame(
        ['mafia', 'maniac', 'civilian', 'civilian'],
        [false, false, true, true],
    );

    expect((new WinConditionChecker())->evaluate($game))->toBe(GameResultEnum::TownWon);
});

it('lets a solo killer take the last-standing victory', function () {
    $game = resultGame(
        ['mafia', 'maniac', 'civilian'],
        [false, true, false],
    );

    expect((new WinConditionChecker())->evaluate($game))->toBe(GameResultEnum::SoloWon);
});
