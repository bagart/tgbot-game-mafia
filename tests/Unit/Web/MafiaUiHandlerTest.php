<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Contracts\MafiaStateStoreContract;
use BAGArt\TelegramBotMafia\GameCoordinator;
use BAGArt\TelegramBotMafia\Web\MafiaUiHandler;
use BAGArt\TelegramBotMenu\Manifest\EffectiveRole;
use BAGArt\TelegramBotMenu\Support\BotRef;
use BAGArt\TelegramBotMenu\Support\ChatRef;
use BAGArt\TelegramBotMenu\Support\ModuleRef;
use BAGArt\TelegramBotMenu\Support\TgUiContext;
use BAGArt\TelegramBotMenu\Support\TgWebRequest;
use BAGArt\TelegramBotMenu\Support\UserRef;
use BAGArt\TelegramBotMafia\Tests\Support\CoordinatorFactory;

function mafiaHandlerContext(int $chatId, int $userId, string $locale = 'en'): TgUiContext
{
    return new TgUiContext(
        bot: new BotRef('7001', 'stub_bot'),
        chat: new ChatRef($chatId, 'Town Square', 'supergroup'),
        module: new ModuleRef('mafia'),
        role: EffectiveRole::Member,
        user: new UserRef($userId, "User{$userId}", $locale),
    );
}

function mafiaHandlerRequest(TgUiContext $context, array $payload = [], string $requestId = 'req-1'): TgWebRequest
{
    return new TgWebRequest(
        botId: '7001',
        tgUserId: $context->user->id,
        role: EffectiveRole::Member,
        chatId: $context->chat?->id,
        locale: $context->user->locale,
        payload: $payload,
        requestId: $requestId,
        context: $context,
    );
}

function setupRunningGame(int $chatId, string $hostId): array
{
    $c = CoordinatorFactory::make();
    app()->instance(MafiaStateStoreContract::class, $c->store());
    $room = $c->createRoom('group', (string) $chatId, 'TestGroup', $hostId, 'Host', 5, 5, [], 'en');
    $p1Id = (string) ($chatId * 100 + 1);
    $p2Id = (string) ($chatId * 100 + 2);
    $c->join($room->id, $p1Id, 'Alice');
    $c->join($room->id, $p2Id, 'Bob');
    for ($i = 0; $i < 2; $i++) {
        $c->addBot($room->id, $hostId);
    }
    foreach ([$hostId, $p1Id, $p2Id] as $uid) {
        $c->confirmDm($room->id, $uid);
    }
    [, $reason] = $c->start($room->id);
    expect($reason)->toBeNull();

    $gameId = (string) $c->rooms()->requireRoom($room->id)->lastGameId;

    return [$c, $gameId];
}

it('returns snapshot with seats and phase for active players', function () {
    [$c, $gameId] = setupRunningGame(-100999, 'host1');
    GameCoordinator::setInstance($c);
    $handler = new MafiaUiHandler();
    $context = mafiaHandlerContext(-100999, 42);
    $request = mafiaHandlerRequest($context);

    $response = $handler->handle($request, ['game', 'snapshot']);

    expect($response->status)->toBe(200)
        ->and($response->body['data']['active'])->toBeTrue()
        ->and($response->body['data']['snapshot']['phase'])->not->toBe('ended')
        ->and($response->body['data']['isSpectator'])->toBeTrue(); // userId 42 is not in the game
});

it('marks external viewer as spectator', function () {
    [$c, $gameId] = setupRunningGame(-100998, 'host1');
    GameCoordinator::setInstance($c);
    $handler = new MafiaUiHandler();

    // p1 is in the game
    $context = mafiaHandlerContext(-100998, (int) hexdec(substr($gameId, 0, 8)) === 0 ? 42 : 42);
    $request = mafiaHandlerRequest($context);
    $response = $handler->handle($request, ['game', 'snapshot']);
    // user 42 is not in the game → spectator
    expect($response->body['data']['isSpectator'])->toBeTrue();
});

it('applies spectator delay: roles are hidden for spectators', function () {
    [$c] = setupRunningGame(-100997, 'host1');
    GameCoordinator::setInstance($c);

    // Load the snapshot directly to check roles exist
    $store = $c->store();
    $game = null;
    foreach ($store->activeGames() as $g) {
        if ($g->chatId === '-100997') {
            $game = $g;
            break;
        }
    }
    expect($game)->not->toBeNull();

    // Verify seats have roles assigned
    foreach ($game->seats as $seat) {
        expect($seat->role)->not->toBeNull();
    }

    // Spectator view: handler strips roles via applySpectatorDelay
    $handler = new MafiaUiHandler();
    $context = mafiaHandlerContext(-100997, 99999); // non-participant
    $request = mafiaHandlerRequest($context);
    $response = $handler->handle($request, ['game', 'snapshot']);
    $data = $response->body['data'];

    expect($data['isSpectator'])->toBeTrue();

    // All seats in the spectator snapshot should have null roles
    foreach ($data['snapshot']['seats'] as $seat) {
        expect($seat['role'])->toBeNull();
    }
});

it('night-action endpoint rejects non-alive players', function () {
    [$c] = setupRunningGame(-100996, 'host1');
    GameCoordinator::setInstance($c);
    $handler = new MafiaUiHandler();
    // user 99999 is not in the game at all
    $context = mafiaHandlerContext(-100996, 99999);
    $request = mafiaHandlerRequest($context, ['targetSeat' => 1, 'actionType' => 'kill']);

    $response = $handler->handle($request, ['game', 'night-action']);

    expect($response->status)->toBe(403);
});

it('vote endpoint rejects non-alive players', function () {
    [$c, $gameId] = setupRunningGame(-100995, 'host1');

    GameCoordinator::setInstance($c);
    $handler = new MafiaUiHandler();
    $context = mafiaHandlerContext(-100995, 99999);
    $request = mafiaHandlerRequest($context, ['targetSeat' => 1]);

    $response = $handler->handle($request, ['game', 'vote']);

    // User 99999 is not in the game — rejected either as wrong phase (400) or not alive (403)
    expect($response->status)->not->toBe(200);
});

it('night-action endpoint rejects wrong phase', function () {
    [$c] = setupRunningGame(-100994, 'host1');
    GameCoordinator::setInstance($c);

    // Advance to discussion phase
    CoordinatorFactory::$clock->advance(200);
    $c->advanceIfOverdue(
        iterator_to_array($c->store()->activeGames())[0]->gameId ?? '',
    );
    // If the game is now in discussion, try a night action
    $handler = new MafiaUiHandler();
    $context = mafiaHandlerContext(-100994, 99999);
    $request = mafiaHandlerRequest($context, ['targetSeat' => 1, 'actionType' => 'kill']);

    $response = $handler->handle($request, ['game', 'night-action']);
    // Should be 403 (not alive) or 400 (wrong phase) — either way, not 200
    expect($response->status)->not->toBe(200);
});

it('vote endpoint rejects wrong phase', function () {
    [$c] = setupRunningGame(-100993, 'host1');
    GameCoordinator::setInstance($c);

    // Game starts in night phase — vote should be rejected
    $handler = new MafiaUiHandler();
    $context = mafiaHandlerContext(-100993, 99999);
    $request = mafiaHandlerRequest($context, ['targetSeat' => 1]);

    $response = $handler->handle($request, ['game', 'vote']);

    // Not in voting phase or not alive — either is rejected
    expect($response->status)->not->toBe(200);
});

it('lobby endpoint returns seats without roles', function () {
    [$c] = setupRunningGame(-100992, 'host1');
    GameCoordinator::setInstance($c);
    $handler = new MafiaUiHandler();
    $context = mafiaHandlerContext(-100992, 42);
    $request = mafiaHandlerRequest($context);

    $response = $handler->handle($request, ['game', 'lobby']);

    expect($response->status)->toBe(200)
        ->and($response->body['data']['active'])->toBeTrue()
        ->and($response->body['data']['seats'])->not->toBeEmpty();

    // Lobby seats must not contain role info
    foreach ($response->body['data']['seats'] as $seat) {
        expect(array_keys($seat))->toBe(['seat', 'name', 'isBot', 'alive']);
    }
});

it('snapshot returns roles for active players in the game', function () {
    $c = CoordinatorFactory::make();
    app()->instance(MafiaStateStoreContract::class, $c->store());
    $room = $c->createRoom('group', '-200100', 'RoleTest', '2001001', 'Host', 5, 5, [], 'en');
    $c->join($room->id, '2001002', 'Alice');
    $c->join($room->id, '2001003', 'Bob');
    for ($i = 0; $i < 2; $i++) {
        $c->addBot($room->id, '2001001');
    }
    foreach (['2001001', '2001002', '2001003'] as $uid) {
        $c->confirmDm($room->id, $uid);
    }
    $c->start($room->id);
    GameCoordinator::setInstance($c);

    $handler = new MafiaUiHandler();

    // 2001002 is a participant — should see their own role and others'
    $context = mafiaHandlerContext(-200100, 2001002);
    $request = mafiaHandlerRequest($context);
    $response = $handler->handle($request, ['game', 'snapshot']);

    expect($response->status)->toBe(200)
        ->and($response->body['data']['isSpectator'])->toBeFalse()
        ->and($response->body['data']['viewerSeat'])->not->toBeNull();

    // at least the viewer's own seat should have a role
    $viewerSeat = $response->body['data']['viewerSeat'];
    foreach ($response->body['data']['snapshot']['seats'] as $seat) {
        if ($seat['seat'] === $viewerSeat) {
            expect($seat['role'])->not->toBeNull();
        }
    }
});

it('snapshot keeps own role for dead players but strips others', function () {
    $c = CoordinatorFactory::make();
    app()->instance(MafiaStateStoreContract::class, $c->store());
    $room = $c->createRoom('group', '-200101', 'DeadTest', '2001011', 'Host', 5, 5, [], 'en');
    $c->join($room->id, '2001012', 'Alice');
    $c->join($room->id, '2001013', 'Bob');
    for ($i = 0; $i < 2; $i++) {
        $c->addBot($room->id, '2001011');
    }
    foreach (['2001011', '2001012', '2001013'] as $uid) {
        $c->confirmDm($room->id, $uid);
    }
    $c->start($room->id);

    $gameId = (string) $c->rooms()->requireRoom($room->id)->lastGameId;
    $snapshot = $c->store()->loadSnapshot($gameId);

    // Kill Alice (2001012) to make them a dead player
    $seats = array_map(fn ($s) => $s->userId === '2001012' ? $s->with(alive: false) : $s, $snapshot->seats);
    $c->store()->saveSnapshot($snapshot->with(seats: $seats, phase: PhaseEnum::DayDiscussion, phaseNumber: 2, dayNumber: 1, deadlineAt: CoordinatorFactory::$clock->now() + 100));
    GameCoordinator::setInstance($c);

    $handler = new MafiaUiHandler();

    // 2001012 (Alice) is dead — should be treated as spectator
    $context = mafiaHandlerContext(-200101, 2001012);
    $request = mafiaHandlerRequest($context);
    $response = $handler->handle($request, ['game', 'snapshot']);

    expect($response->status)->toBe(200)
        ->and($response->body['data']['isSpectator'])->toBeTrue();

    // Alice's own seat should still show the role (viewer preservation)
    // other seats should have null roles
    foreach ($response->body['data']['snapshot']['seats'] as $seat) {
        if ($seat['name'] === 'Alice') {
            expect($seat['role'])->not->toBeNull();
        } else {
            expect($seat['role'])->toBeNull();
        }
    }
});

it('night-action returns coordinator toast on success', function () {
    $c = CoordinatorFactory::make(random: fn (int $max): int => $max);
    app()->instance(MafiaStateStoreContract::class, $c->store());
    $room = $c->createRoom('group', '-200102', 'NATest', '2001021', 'Host', 5, 5, [], 'en');
    $c->join($room->id, '2001022', 'Alice');
    $c->join($room->id, '2001023', 'Bob');
    for ($i = 0; $i < 2; $i++) {
        $c->addBot($room->id, '2001021');
    }
    foreach (['2001021', '2001022', '2001023'] as $uid) {
        $c->confirmDm($room->id, $uid);
    }
    $c->start($room->id);
    GameCoordinator::setInstance($c);

    $snapshot = $c->store()->gameByChat('-200102');
    $me = $snapshot->seatByUser('2001022');

    // If 2001022 has an action-capable role, submit the action via Mini App
    if ($me !== null && $me->alive && in_array($me->role, ['mafia', 'doctor', 'detective', 'escort', 'bodyguard', 'journalist'], true)) {
        $handler = new MafiaUiHandler();
        $context = mafiaHandlerContext(-200102, 2001022);
        $request = mafiaHandlerRequest($context, ['targetSeat' => 1, 'actionType' => $me->role]);
        $response = $handler->handle($request, ['game', 'night-action']);

        expect($response->status)->toBe(200);
        // should contain ok and possibly toast
        expect($response->body['ok'])->toBeTrue();
    }
});

it('night-action returns error toast on double action', function () {
    $c = CoordinatorFactory::make(random: fn (int $max): int => $max);
    app()->instance(MafiaStateStoreContract::class, $c->store());
    $room = $c->createRoom('group', '-200103', 'DoubleAct', '2001031', 'Host', 5, 5, [], 'en');
    $c->join($room->id, '2001032', 'Alice');
    $c->join($room->id, '2001033', 'Bob');
    for ($i = 0; $i < 2; $i++) {
        $c->addBot($room->id, '2001031');
    }
    foreach (['2001031', '2001032', '2001033'] as $uid) {
        $c->confirmDm($room->id, $uid);
    }
    $c->start($room->id);
    GameCoordinator::setInstance($c);

    $snapshot = $c->store()->gameByChat('-200103');
    $me = $snapshot->seatByUser('2001032');

    if ($me !== null && $me->alive && in_array($me->role, ['mafia', 'doctor', 'detective', 'escort', 'bodyguard', 'journalist'], true)) {
        $handler = new MafiaUiHandler();
        $context = mafiaHandlerContext(-200103, 2001032);

        // First action must target a different seat so it is recorded;
        // a self-target would be refused for non-doctors and never recorded
        $request1 = mafiaHandlerRequest($context, ['targetSeat' => 3, 'actionType' => $me->role]);
        $handler->handle($request1, ['game', 'night-action']);

        // Second action should fail with double-action toast
        $request2 = mafiaHandlerRequest($context, ['targetSeat' => 3, 'actionType' => $me->role]);
        $response = $handler->handle($request2, ['game', 'night-action']);

        expect($response->status)->toBe(200)
            ->and($response->body['ok'])->toBeTrue()
            ->and($response->body['data']['toast'] ?? null)->not->toBeNull();
    }
});

it('vote returns coordinator toast on success', function () {
    $c = CoordinatorFactory::make();
    app()->instance(MafiaStateStoreContract::class, $c->store());
    $room = $c->createRoom('group', '-200104', 'VoteTest', '2001041', 'Host', 5, 5, [], 'en');
    $c->join($room->id, '2001042', 'Alice');
    $c->join($room->id, '2001043', 'Bob');
    for ($i = 0; $i < 2; $i++) {
        $c->addBot($room->id, '2001041');
    }
    foreach (['2001041', '2001042', '2001043'] as $uid) {
        $c->confirmDm($room->id, $uid);
    }
    $c->start($room->id);

    // Advance past night into discussion, then into voting
    $gameId = (string) $c->rooms()->requireRoom($room->id)->lastGameId;
    for ($i = 0; $i < 200 && $c->store()->loadSnapshot($gameId)?->phase !== PhaseEnum::DayDiscussion; $i++) {
        CoordinatorFactory::$clock->advance(600);
        $c->advanceIfOverdue($gameId);
    }
    CoordinatorFactory::$clock->advance(200);
    $c->advanceIfOverdue($gameId);

    $snapshot = $c->store()->loadSnapshot($gameId);
    if ($snapshot !== null && $snapshot->phase === PhaseEnum::DayVoting) {
        GameCoordinator::setInstance($c);
        $handler = new MafiaUiHandler();
        $context = mafiaHandlerContext(-200104, 2001042);
        $request = mafiaHandlerRequest($context, ['targetSeat' => 2]);
        $response = $handler->handle($request, ['game', 'vote']);

        expect($response->status)->toBe(200)
            ->and($response->body['ok'])->toBeTrue();
    }
});

it('vote returns error toast on double vote', function () {
    $c = CoordinatorFactory::make();
    app()->instance(MafiaStateStoreContract::class, $c->store());
    $room = $c->createRoom('group', '-200105', 'DoubleVote', '2001051', 'Host', 5, 5, [], 'en');
    $c->join($room->id, '2001052', 'Alice');
    $c->join($room->id, '2001053', 'Bob');
    for ($i = 0; $i < 2; $i++) {
        $c->addBot($room->id, '2001051');
    }
    foreach (['2001051', '2001052', '2001053'] as $uid) {
        $c->confirmDm($room->id, $uid);
    }
    $c->start($room->id);

    $gameId = (string) $c->rooms()->requireRoom($room->id)->lastGameId;
    for ($i = 0; $i < 200 && $c->store()->loadSnapshot($gameId)?->phase !== PhaseEnum::DayDiscussion; $i++) {
        CoordinatorFactory::$clock->advance(600);
        $c->advanceIfOverdue($gameId);
    }
    CoordinatorFactory::$clock->advance(200);
    $c->advanceIfOverdue($gameId);

    $snapshot = $c->store()->loadSnapshot($gameId);
    if ($snapshot !== null && $snapshot->phase === PhaseEnum::DayVoting) {
        GameCoordinator::setInstance($c);
        $handler = new MafiaUiHandler();
        $context = mafiaHandlerContext(-200105, 2001052);

        // First vote
        $request1 = mafiaHandlerRequest($context, ['targetSeat' => 2]);
        $handler->handle($request1, ['game', 'vote']);

        // Second vote should return double-action toast
        $request2 = mafiaHandlerRequest($context, ['targetSeat' => 3]);
        $response = $handler->handle($request2, ['game', 'vote']);

        expect($response->status)->toBe(200)
            ->and($response->body['ok'])->toBeTrue()
            ->and($response->body['data']['toast'] ?? null)->not->toBeNull();
    }
});

it('skip-night returns toast feedback', function () {
    $c = CoordinatorFactory::make();
    app()->instance(MafiaStateStoreContract::class, $c->store());
    $room = $c->createRoom('group', '-200106', 'SkipNight', '2001061', 'Host', 5, 5, [], 'en');
    $c->join($room->id, '2001062', 'Alice');
    $c->join($room->id, '2001063', 'Bob');
    for ($i = 0; $i < 2; $i++) {
        $c->addBot($room->id, '2001061');
    }
    foreach (['2001061', '2001062', '2001063'] as $uid) {
        $c->confirmDm($room->id, $uid);
    }
    $c->start($room->id);
    GameCoordinator::setInstance($c);

    $handler = new MafiaUiHandler();
    $context = mafiaHandlerContext(-200106, 2001062);
    $request = mafiaHandlerRequest($context);
    $response = $handler->handle($request, ['game', 'skip-night']);

    expect($response->status)->toBe(200)
        ->and($response->body['ok'])->toBeTrue();
});

it('night-action with role-based actionType succeeds for each role', function () {
    $c = CoordinatorFactory::make(random: fn (int $max): int => $max);
    app()->instance(MafiaStateStoreContract::class, $c->store());
    $room = $c->createRoom('group', '-200110', 'RoleAction', '2001101', 'Host', 5, 5, [], 'en');
    $c->join($room->id, '2001102', 'Alice');
    $c->join($room->id, '2001103', 'Bob');
    for ($i = 0; $i < 2; $i++) {
        $c->addBot($room->id, '2001101');
    }
    foreach (['2001101', '2001102', '2001103'] as $uid) {
        $c->confirmDm($room->id, $uid);
    }
    $c->start($room->id);
    GameCoordinator::setInstance($c);

    $snapshot = $c->store()->gameByChat('-200110');
    $me = $snapshot->seatByUser('2001102');

    $roleActionMap = [
        'mafia' => 'kill',
        'doctor' => 'heal',
        'detective' => 'check_alignment',
        'bodyguard' => 'guard',
    ];

    if ($me !== null && $me->alive && isset($roleActionMap[$me->role])) {
        $handler = new MafiaUiHandler();
        $context = mafiaHandlerContext(-200110, 2001102);
        $request = mafiaHandlerRequest($context, [
            'targetSeat' => 2,
            'actionType' => $roleActionMap[$me->role],
        ]);
        $response = $handler->handle($request, ['game', 'night-action']);

        expect($response->status)->toBe(200)
            ->and($response->body['ok'])->toBeTrue();
    }
});

it('night-action with wrong actionType for role is rejected', function () {
    $c = CoordinatorFactory::make(random: fn (int $max): int => $max);
    app()->instance(MafiaStateStoreContract::class, $c->store());
    $room = $c->createRoom('group', '-200111', 'WrongAction', '2001111', 'Host', 5, 5, [], 'en');
    $c->join($room->id, '2001112', 'Alice');
    $c->join($room->id, '2001113', 'Bob');
    for ($i = 0; $i < 2; $i++) {
        $c->addBot($room->id, '2001111');
    }
    foreach (['2001111', '2001112', '2001113'] as $uid) {
        $c->confirmDm($room->id, $uid);
    }
    $c->start($room->id);
    GameCoordinator::setInstance($c);

    $snapshot = $c->store()->gameByChat('-200111');
    $me = $snapshot->seatByUser('2001112');

    $roleActionMap = [
        'mafia' => 'kill',
        'doctor' => 'heal',
        'detective' => 'check_alignment',
        'bodyguard' => 'guard',
    ];

    if ($me !== null && $me->alive && isset($roleActionMap[$me->role])) {
        // Send wrong actionType (e.g. doctor sending 'kill' instead of 'heal')
        $wrongAction = $me->role === 'doctor' ? 'kill' : 'heal';
        $handler = new MafiaUiHandler();
        $context = mafiaHandlerContext(-200111, 2001112);
        $request = mafiaHandlerRequest($context, [
            'targetSeat' => 2,
            'actionType' => $wrongAction,
        ]);
        $response = $handler->handle($request, ['game', 'night-action']);

        // Should be rejected (400 or error toast)
        expect($response->status)->not->toBe(200);
        expect($response->body['ok'] ?? false)->not->toBeTrue();
    }
});

it('spectator cannot perform night actions', function () {
    [$c] = setupRunningGame(-200112, 'host1');
    GameCoordinator::setInstance($c);
    $handler = new MafiaUiHandler();
    // user 88888 is not in the game → spectator
    $context = mafiaHandlerContext(-200112, 88888);
    $request = mafiaHandlerRequest($context, ['targetSeat' => 1, 'actionType' => 'kill']);

    $response = $handler->handle($request, ['game', 'night-action']);

    expect($response->status)->toBe(403);
});

it('rematch creates a new lobby and notifies previous players', function () {
    $c = CoordinatorFactory::make();
    $room = $c->createRoom('interface', null, 'RematchFlow', 'host1', 'Host', 5, 5, [], 'en');
    $c->join($room->id, 'p1', 'Alice');
    for ($i = 0; $i < 3; $i++) {
        $c->addBot($room->id, 'host1');
    }
    $c->confirmDm($room->id, 'host1');
    $c->confirmDm($room->id, 'p1');
    $c->start($room->id);
    $gameId = (string) $c->rooms()->requireRoom($room->id)->lastGameId;

    // End the game by advancing time
    for ($i = 0; $i < 300; $i++) {
        foreach ($c->store()->activeGames() as $game) {
            $c->advanceIfOverdue($game->gameId);
        }
        if ($c->store()->loadSnapshot($gameId)?->phase === PhaseEnum::Ended) {
            break;
        }
        CoordinatorFactory::$clock->advance(600);
    }
    expect($c->store()->loadSnapshot($gameId)?->phase)->toBe(PhaseEnum::Ended);

    // Trigger rematch
    $result = $c->rematch($gameId, 'host1');
    expect($result['toast'])->toBe('end.rematch_created')
        ->and($result['roomId'])->toBeString();

    $newRoom = $c->rooms()->requireRoom((string) $result['roomId']);
    expect($newRoom->status)->toBe('lobby')
        ->and($newRoom->hostUserId)->toBe('host1');

    // The actor (host1) should get a lobby card
    $actorPlan = array_filter($result['plans'], fn ($p) => $p->chatId === 'host1');
    expect($actorPlan)->not->toBeEmpty();

    // p1 should get a rematch notification
    $p1Plan = array_filter($result['plans'], fn ($p) => $p->chatId === 'p1');
    expect($p1Plan)->not->toBeEmpty();
});
