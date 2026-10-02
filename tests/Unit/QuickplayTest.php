<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\GameCoordinator;
use BAGArt\TelegramBotMafia\Quickplay\QuickplayQueueEntry;
use BAGArt\TelegramBotMafia\State\InMemoryQuickplayQueue;
use BAGArt\TelegramBotMafia\Tests\Support\CoordinatorFactory;
use BAGArt\TelegramBotMafia\Tests\Support\FakeClock;

beforeEach(function () {
    $this->clock = new FakeClock();
    $this->queue = new InMemoryQuickplayQueue($this->clock);
});

it('enqueues a player and returns correct position', function () {
    $pos1 = $this->queue->join('bot1', 'u1', 'Alice');
    $pos2 = $this->queue->join('bot1', 'u2', 'Bob');
    $pos3 = $this->queue->join('bot1', 'u3', 'Charlie');

    expect($pos1)->toBe(0)
        ->and($pos2)->toBe(1)
        ->and($pos3)->toBe(2)
        ->and($this->queue->count('bot1'))->toBe(3);
});

it('is idempotent on repeated joins (refreshes position)', function () {
    $this->queue->join('bot1', 'u1', 'Alice');
    $this->queue->join('bot1', 'u2', 'Bob');
    $pos = $this->queue->join('bot1', 'u1', 'Alice');

    expect($pos)->toBe(0)
        ->and($this->queue->count('bot1'))->toBe(2);
});

it('cancels a queued player', function () {
    $this->queue->join('bot1', 'u1', 'Alice');
    $this->queue->join('bot1', 'u2', 'Bob');
    $this->queue->cancel('bot1', 'u1');

    expect($this->queue->count('bot1'))->toBe(1)
        ->and($this->queue->contains('bot1', 'u1'))->toBeFalse()
        ->and($this->queue->contains('bot1', 'u2'))->toBeTrue();
});

it('cancel on non-existent player is a no-op', function () {
    $this->queue->cancel('bot1', 'ghost');
    expect($this->queue->count('bot1'))->toBe(0);
});

it('isolates bots from each other', function () {
    $this->queue->join('bot1', 'u1', 'Alice');
    $this->queue->join('bot2', 'u2', 'Bob');

    expect($this->queue->count('bot1'))->toBe(1)
        ->and($this->queue->count('bot2'))->toBe(1)
        ->and($this->queue->contains('bot1', 'u2'))->toBeFalse()
        ->and($this->queue->contains('bot2', 'u1'))->toBeFalse();
});

it('returns entries sorted by join time', function () {
    $this->clock->advance(10);
    $this->queue->join('bot1', 'u1', 'Alice');
    $this->clock->advance(5);
    $this->queue->join('bot1', 'u2', 'Bob');
    $this->clock->advance(5);
    $this->queue->join('bot1', 'u3', 'Charlie');

    $entries = $this->queue->entries('bot1');
    expect($entries)->toHaveCount(3)
        ->and($entries[0]->userId)->toBe('u1')
        ->and($entries[1]->userId)->toBe('u2')
        ->and($entries[2]->userId)->toBe('u3');
});

it('drains expired entries older than threshold', function () {
    $this->queue->join('bot1', 'u1', 'Alice');
    $this->clock->advance(30);
    $this->queue->join('bot1', 'u2', 'Bob');
    $this->clock->advance(40); // now = start + 70

    $drained = $this->queue->drainExpired('bot1', 60);

    expect($drained)->toHaveCount(1)
        ->and($drained[0]->userId)->toBe('u1')
        ->and($this->queue->count('bot1'))->toBe(1)
        ->and($this->queue->contains('bot1', 'u2'))->toBeTrue();
});

it('drains all when all are expired', function () {
    $this->queue->join('bot1', 'u1', 'Alice');
    $this->queue->join('bot1', 'u2', 'Bob');
    $this->clock->advance(70);

    $drained = $this->queue->drainExpired('bot1', 60);

    expect($drained)->toHaveCount(2)
        ->and($this->queue->count('bot1'))->toBe(0);
});

it('drains nothing when all are fresh', function () {
    $this->queue->join('bot1', 'u1', 'Alice');
    $this->clock->advance(10);

    $drained = $this->queue->drainExpired('bot1', 60);

    expect($drained)->toHaveCount(0)
        ->and($this->queue->count('bot1'))->toBe(1);
});

it('serializes and deserializes entry via JSON round-trip', function () {
    $entry = new QuickplayQueueEntry(userId: 'u1', name: 'Alice', joinedAt: 1700000000);
    $json = $entry->toJson();
    $restored = QuickplayQueueEntry::fromJson($json);

    expect($restored->userId)->toBe('u1')
        ->and($restored->name)->toBe('Alice')
        ->and($restored->joinedAt)->toBe(1700000000);
});

// --- Coordinator integration tests ---

it('joins quickplay queue via coordinator and shows position', function () {
    $c = CoordinatorFactory::make();
    $result = $c->joinQuickplay('bot1', 'u1', 'Alice');

    expect($result['toast'])->toBe('qp.joined')
        ->and($result['plans'])->toHaveCount(1);
});

it('rejects quickplay join when user is already in an active game', function () {
    $c = CoordinatorFactory::make();
    $room = $c->createRoom('interface', null, 'Test', 'u1', 'Alice', 5, 5, [], 'en');
    $c->confirmDm($room->id, 'u1');
    for ($i = 0; $i < 4; $i++) {
        $c->addBot($room->id, 'u1');
    }
    $c->start($room->id, 'u1');

    $result = $c->joinQuickplay('bot1', 'u1', 'Alice');
    expect($result['toast'])->toBe('errors.already_in_other_game');
});

it('idempotent quickplay join shows already-queued message', function () {
    $c = CoordinatorFactory::make();
    $c->joinQuickplay('bot1', 'u1', 'Alice');
    $result = $c->joinQuickplay('bot1', 'u1', 'Alice');

    expect($result['toast'])->toBe('qp.already_queued')
        ->and($result['plans'])->toHaveCount(1);
});

it('auto-starts game when threshold reached', function () {
    $c = CoordinatorFactory::make();
    $botId = 'bot1';

    // Add 5 players
    for ($i = 0; $i < 5; $i++) {
        $userId = "u{$i}";
        $c->confirmDm(
            // need room id first — joinQuickplay creates the room
            // so we join all 5, last one triggers start
            '',
            $userId,
        );
    }

    // Reset: we need to test the coordinator flow properly
    $c2 = CoordinatorFactory::make();
    for ($i = 0; $i < 4; $i++) {
        $c2->joinQuickplay('bot1', "u{$i}", "Player{$i}");
    }
    // 4th player: should not start yet
    expect($c2->rooms()->openRooms())->toHaveCount(0);

    $result = $c2->joinQuickplay('bot1', 'u4', 'Player4');
    expect($result['toast'])->toBe('qp.game_started')
        ->and($result['roomId'])->toBeString();

    // Verify a game was created and started
    $rooms = $c2->rooms()->openRooms();
    expect($rooms)->not->toBeEmpty();
});

it('cancels quickplay via coordinator', function () {
    $c = CoordinatorFactory::make();
    $c->joinQuickplay('bot1', 'u1', 'Alice');
    $result = $c->cancelQuickplay('bot1', 'u1');

    expect($result['toast'])->toBe('qp.cancelled')
        ->and($result['plans'])->toHaveCount(1);
});

it('cancel on non-queued user returns not_queued', function () {
    $c = CoordinatorFactory::make();
    $result = $c->cancelQuickplay('bot1', 'ghost');

    expect($result['toast'])->toBe('qp.not_queued');
});

it('drains expired quickplay entries via coordinator', function () {
    $c = CoordinatorFactory::make();
    $c->joinQuickplay('bot1', 'u1', 'Alice');
    CoordinatorFactory::$clock->advance(70);
    $plans = $c->drainExpiredQuickplay('bot1');

    expect($plans)->toHaveCount(1)
        ->and($plans[0]->chatId)->toBe('u1');
});

it('returns empty plans when queue unavailable', function () {
    $c = CoordinatorFactory::make(
        settings: new \BAGArt\TelegramBotMafia\Settings\MafiaSettings(),
    );
    // Create coordinator without quickplay queue (null)
    $noQueue = new GameCoordinator(
        rooms: $c->rooms(),
        store: $c->store(),
        profiles: $c->profiles(),
        clock: CoordinatorFactory::$clock,
        langBasePath: dirname(__DIR__, 2).'/resources/lang',
        brain: new \BAGArt\TelegramBotMafia\Bots\HeuristicBrain(fn (int $max): int => 0),
        quickplayQueue: null,
    );

    $result = $noQueue->joinQuickplay('bot1', 'u1', 'Alice');
    expect($result['toast'])->toBe('onb.coming_soon');
});

it('bot isolation: different bots have separate queues', function () {
    $c = CoordinatorFactory::make();
    $c->joinQuickplay('bot1', 'u1', 'Alice');
    $c->joinQuickplay('bot2', 'u2', 'Bob');

    expect($c->drainExpiredQuickplay('bot1'))->toHaveCount(0)
        ->and($c->drainExpiredQuickplay('bot2'))->toHaveCount(0);

    CoordinatorFactory::$clock->advance(70);

    $drained1 = $c->drainExpiredQuickplay('bot1');
    $drained2 = $c->drainExpiredQuickplay('bot2');
    expect($drained1)->toHaveCount(1)
        ->and($drained1[0]->chatId)->toBe('u1')
        ->and($drained2)->toHaveCount(1)
        ->and($drained2[0]->chatId)->toBe('u2');
});

it('concurrent join idempotency: same user joining twice is handled', function () {
    $c = CoordinatorFactory::make();
    $r1 = $c->joinQuickplay('bot1', 'u1', 'Alice');
    $r2 = $c->joinQuickplay('bot1', 'u1', 'Alice');

    expect($r1['toast'])->toBe('qp.joined')
        ->and($r2['toast'])->toBe('qp.already_queued');

    $queue = new InMemoryQuickplayQueue(CoordinatorFactory::$clock);
    $queue->join('bot1', 'u1', 'Alice');
    expect($queue->count('bot1'))->toBe(1);
});

it('quickplay sweep sends expiry notifications', function () {
    $c = CoordinatorFactory::make();
    $c->joinQuickplay('bot1', 'u1', 'Alice');
    $c->joinQuickplay('bot1', 'u2', 'Bob');

    // Not expired yet
    $plans = $c->drainExpiredQuickplay('bot1');
    expect($plans)->toHaveCount(0);

    // After 60s both expire
    CoordinatorFactory::$clock->advance(70);
    $plans = $c->drainExpiredQuickplay('bot1');
    expect($plans)->toHaveCount(2);
});

it('quickplay does not start with fewer than threshold players', function () {
    $c = CoordinatorFactory::make();
    for ($i = 0; $i < 3; $i++) {
        $c->joinQuickplay('bot1', "u{$i}", "P{$i}");
    }

    expect($c->rooms()->openRooms())->toHaveCount(0);
});
