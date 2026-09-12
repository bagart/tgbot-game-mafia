<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\State\InMemoryMafiaDlq;

it('pushes and pops entries', function () {
    $dlq = new InMemoryMafiaDlq();

    $dlq->push('bot1', [
        'callbackData' => 'm:join:game1:null',
        'botId' => 'bot1',
        'userId' => 'user1',
        'exception' => 'test error',
        'failedAt' => '2026-09-12T00:00:00+00:00',
    ]);

    expect($dlq->pendingCount('bot1'))->toBe(1);

    $entries = $dlq->pop('bot1', 5);
    expect($entries)->toHaveCount(1);
    expect($entries[0]['callbackData'])->toBe('m:join:game1:null');
    expect($dlq->pendingCount('bot1'))->toBe(0);
});

it('acks entries', function () {
    $dlq = new InMemoryMafiaDlq();

    $dlq->push('bot1', [
        'callbackData' => 'm:join:game1:null',
        'botId' => 'bot1',
        'userId' => 'user1',
        'exception' => 'test error',
        'failedAt' => '2026-09-12T00:00:00+00:00',
    ]);

    $dlq->ack('bot1', 'm:join:game1:null');
    expect($dlq->pendingCount('bot1'))->toBe(0);
});

it('respects limit on pop', function () {
    $dlq = new InMemoryMafiaDlq();

    for ($i = 0; $i < 10; $i++) {
        $dlq->push('bot1', [
            'callbackData' => "m:join:game{$i}:null",
            'botId' => 'bot1',
            'userId' => 'user1',
            'exception' => 'test error',
            'failedAt' => '2026-09-12T00:00:00+00:00',
        ]);
    }

    expect($dlq->pendingCount('bot1'))->toBe(10);

    $entries = $dlq->pop('bot1', 3);
    expect($entries)->toHaveCount(3);
    expect($dlq->pendingCount('bot1'))->toBe(7);
});

it('isolates bots', function () {
    $dlq = new InMemoryMafiaDlq();

    $dlq->push('bot1', [
        'callbackData' => 'm:join:game1:null',
        'botId' => 'bot1',
        'userId' => 'user1',
        'exception' => 'test error',
        'failedAt' => '2026-09-12T00:00:00+00:00',
    ]);

    $dlq->push('bot2', [
        'callbackData' => 'm:join:game2:null',
        'botId' => 'bot2',
        'userId' => 'user2',
        'exception' => 'test error',
        'failedAt' => '2026-09-12T00:00:00+00:00',
    ]);

    expect($dlq->pendingCount('bot1'))->toBe(1);
    expect($dlq->pendingCount('bot2'))->toBe(1);

    $entries = $dlq->pop('bot1', 5);
    expect($entries)->toHaveCount(1);
    expect($entries[0]['callbackData'])->toBe('m:join:game1:null');
    expect($dlq->pendingCount('bot1'))->toBe(0);
    expect($dlq->pendingCount('bot2'))->toBe(1);
});
