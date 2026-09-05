<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\ClockContract;
use BAGArt\TelegramBotMafia\Contracts\MafiaNotesStoreContract;
use BAGArt\TelegramBotMafia\Core\Enums\MarkKind;

/**
 * Process-memory notes store: default for tests and pure-PHP usage.
 * Production swaps in a Redis-backed implementation of the same contract;
 * the data is pure values, so nothing else has to change.
 *
 * Expiry: every write records a timestamp; prune() drops per-user buckets
 * that were not written since the given point (game end + grace safety net
 * — callers still wipe rooms explicitly at game end).
 */
final class InMemoryMafiaNotesStore implements MafiaNotesStoreContract
{
    public function __construct(private readonly ClockContract $clock = new SystemClock())
    {
    }

    /** @var array<string, array<int, array<string, MarkKind>>> roomId:userId => seat => kindValue => kind */
    private array $marks = [];

    /** @var array<string, int> roomId:userId => last write timestamp */
    private array $lastWriteAt = [];

    /** @var array<string, int> roomId:userId => monotonic revision */
    private array $notesRev = [];

    public function toggle(string $roomId, string $userId, int $seat, MarkKind $kind): bool
    {
        $key = $roomId.':'.$userId;
        if (($this->marks[$key][$seat][$kind->value] ?? null) instanceof MarkKind) {
            unset($this->marks[$key][$seat][$kind->value]);
            $on = false;
        } else {
            $this->marks[$key][$seat][$kind->value] = $kind;
            $on = true;
        }
        if (empty($this->marks[$key][$seat])) {
            unset($this->marks[$key][$seat]);
        }
        $this->touch($key);

        return $on;
    }

    public function set(string $roomId, string $userId, int $seat, array $kinds): void
    {
        $key = $roomId.':'.$userId;
        $normalized = [];
        foreach ($kinds as $kind) {
            $normalized[$kind->value] = $kind;
        }
        if ($normalized === []) {
            unset($this->marks[$key][$seat]);
            $this->touch($key);

            return;
        }
        $this->marks[$key][$seat] = $normalized;
        $this->touch($key);
    }

    public function clear(string $roomId, string $userId, int $seat, ?MarkKind $kind = null): void
    {
        $key = $roomId.':'.$userId;
        if ($kind === null) {
            unset($this->marks[$key][$seat]);
            $this->touch($key);

            return;
        }
        unset($this->marks[$key][$seat][$kind->value]);
        if (empty($this->marks[$key][$seat])) {
            unset($this->marks[$key][$seat]);
        }
        $this->touch($key);
    }

    public function wipeRoom(string $roomId): void
    {
        foreach (array_keys($this->marks) as $key) {
            if (str_starts_with($key, $roomId.':')) {
                // notesRev survives the wipe so long-poll clients observe it
                unset($this->marks[$key], $this->lastWriteAt[$key]);
                $this->touchRevOnly($key);
            }
        }
    }

    public function marks(string $roomId, string $userId): array
    {
        $out = [];
        foreach ($this->marks[$roomId.':'.$userId] ?? [] as $seat => $kinds) {
            $list = array_values($kinds);
            usort($list, fn (MarkKind $a, MarkKind $b): int => $a->value <=> $b->value);
            $out[$seat] = $list;
        }
        ksort($out);

        return $out;
    }

    public function notesRev(string $roomId, string $userId): int
    {
        return $this->notesRev[$roomId.':'.$userId] ?? 0;
    }

    /**
     * Drops per-user note buckets with no write since $beforeTimestamp.
     *
     * @return int number of removed user buckets
     */
    public function prune(int $beforeTimestamp): int
    {
        $removed = 0;
        foreach ($this->marks as $key => $_) {
            if (($this->lastWriteAt[$key] ?? 0) < $beforeTimestamp) {
                unset($this->marks[$key], $this->lastWriteAt[$key]);
                $removed++;
            }
        }

        return $removed;
    }

    private function touch(string $key): void
    {
        $this->lastWriteAt[$key] = $this->clock->now();
        $this->touchRevOnly($key);
    }

    private function touchRevOnly(string $key): void
    {
        $this->notesRev[$key] = ($this->notesRev[$key] ?? 0) + 1;
    }
}
