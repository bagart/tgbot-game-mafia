<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Contracts\ClockContract;
use BAGArt\TelegramBotMafia\Core\Enums\MarkKind;
use BAGArt\TelegramBotMafia\State\InMemoryMafiaNotesStore;

final class MutableFakeClock implements ClockContract
{
    public int $now = 1_000;

    public function now(): int
    {
        return $this->now;
    }
}

function notesStore(?MutableFakeClock $clock = null): InMemoryMafiaNotesStore
{
    return new InMemoryMafiaNotesStore($clock ?? new MutableFakeClock());
}

it('toggles a mark and reports the resulting state', function () {
    $store = notesStore();

    expect($store->toggle('r1', 'u1', 3, MarkKind::Suspect))->toBeTrue()
        ->and($store->marks('r1', 'u1'))->toBe([3 => [MarkKind::Suspect]])
        ->and($store->toggle('r1', 'u1', 3, MarkKind::Suspect))->toBeFalse()
        ->and($store->marks('r1', 'u1'))->toBe([]);
});

it('isolates marks per user in the same room', function () {
    $store = notesStore();
    $store->set('r1', 'a', 2, [MarkKind::Suspect, MarkKind::Doubt]);

    expect($store->marks('r1', 'b'))->toBe([])
        ->and($store->notesRev('r1', 'b'))->toBe(0)
        ->and($store->clear('r1', 'b', 2))->toBeNull()
        ->and($store->marks('r1', 'a'))->toBe([2 => [MarkKind::Doubt, MarkKind::Suspect]]);
});

it('bumps notesRev per user independently on every write', function () {
    $store = notesStore();
    $store->toggle('r1', 'a', 1, MarkKind::Clear);
    $store->toggle('r1', 'a', 1, MarkKind::Clear);

    $beforeB = $store->notesRev('r1', 'b');
    $store->set('r1', 'a', 5, [MarkKind::VoteTarget]);
    $store->clear('r1', 'a', 5);

    expect($store->notesRev('r1', 'a'))->toBe(4)
        ->and($store->notesRev('r1', 'b'))->toBe($beforeB)
        ->and($store->notesRev('r2', 'a'))->toBe(0);
});

it('clears one kind or the whole seat', function () {
    $store = notesStore();
    $store->set('r1', 'u1', 4, [MarkKind::Suspect, MarkKind::Doubt]);

    $store->clear('r1', 'u1', 4, MarkKind::Suspect);
    expect($store->marks('r1', 'u1'))->toBe([4 => [MarkKind::Doubt]]);

    $store->clear('r1', 'u1', 4);
    expect($store->marks('r1', 'u1'))->toBe([]);
});

it('wipes the whole room for everyone but leaves other rooms intact', function () {
    $store = notesStore();
    $store->set('r1', 'a', 1, [MarkKind::Suspect]);
    $store->set('r1', 'b', 2, [MarkKind::Clear]);
    $store->set('r2', 'a', 1, [MarkKind::Doubt]);

    $revA = $store->notesRev('r1', 'a');
    $store->wipeRoom('r1');

    expect($store->marks('r1', 'a'))->toBe([])
        ->and($store->marks('r1', 'b'))->toBe([])
        ->and($store->marks('r2', 'a'))->toBe([1 => [MarkKind::Doubt]])
        ->and($store->notesRev('r1', 'a'))->toBe($revA + 1);
});

it('returns marks keyed by seat with sorted kind lists', function () {
    $store = notesStore();
    $store->set('r1', 'u1', 7, [MarkKind::VoteTarget, MarkKind::Clear]);
    $store->set('r1', 'u1', 2, [MarkKind::Suspect]);

    expect(array_keys($store->marks('r1', 'u1')))->toBe([2, 7])
        ->and($store->marks('r1', 'u1')[7])->toBe([MarkKind::Clear, MarkKind::VoteTarget])
        ->and($store->marks('r1', 'u1')[2])->toBe([MarkKind::Suspect]);
});

it('prunes buckets with no writes since the cutoff timestamp', function () {
    $clock = new MutableFakeClock();
    $store = notesStore($clock);
    $store->set('r1', 'stale', 2, [MarkKind::Clear]);

    $clock->now = 5_000;
    $store->set('r1', 'fresh', 1, [MarkKind::Suspect]);

    expect($store->prune(2_000))->toBe(1)
        ->and($store->marks('r1', 'stale'))->toBe([])
        ->and($store->marks('r1', 'fresh'))->toBe([1 => [MarkKind::Suspect]]);

    expect(notesStore()->prune(0))->toBe(0);
});

it('alternates toggle results across repeated calls on the same mark', function () {
    $store = notesStore();

    $results = [];
    for ($i = 0; $i < 4; $i++) {
        $results[] = $store->toggle('r1', 'u1', 3, MarkKind::Doubt);
    }

    expect($results)->toBe([true, false, true, false])
        ->and($store->notesRev('r1', 'u1'))->toBe(4);
});
