<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\MafiaStateStoreContract;
use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\GameSnapshot;

/**
 * Process-memory store: default for tests and pure-PHP usage. Production
 * swaps in a Redis-backed implementation of the same contract (snapshot JSON
 * + per-game lock); the core never knows the difference.
 */
final class InMemoryMafiaStateStore implements MafiaStateStoreContract
{
    /** @var array<string, GameSnapshot> */
    private array $snapshots = [];

    /** @var array<string, string> userId => gameId */
    private array $byUser = [];

    /** @var array<string, string> chatId => gameId */
    private array $byChat = [];

    /** @var array<string, string> sayLockKey => gameId */
    private array $sayLocks = [];

    /** @var array<string, array<string, bool>> */
    private array $dmConfirmed = [];

    public function saveSnapshot(GameSnapshot $snapshot): void
    {
        $this->snapshots[$snapshot->gameId] = $snapshot;
        $this->byChat = array_filter($this->byChat, fn ($gid) => $gid !== $snapshot->gameId);
        if ($snapshot->chatId !== null && $snapshot->phase !== PhaseEnum::Ended) {
            $this->byChat[$snapshot->chatId] = $snapshot->gameId;
        }
        $this->byUser = array_filter($this->byUser, fn ($gid) => $gid !== $snapshot->gameId);
        if ($snapshot->phase !== PhaseEnum::Ended) {
            foreach ($snapshot->seats as $seat) {
                if (! $seat->isBot) {
                    $this->byUser[$seat->userId] = $snapshot->gameId;
                }
            }
        }
    }

    public function loadSnapshot(string $gameId): ?GameSnapshot
    {
        return $this->snapshots[$gameId] ?? null;
    }

    public function deleteSnapshot(string $gameId): void
    {
        unset($this->snapshots[$gameId]);
    }

    public function gameByChat(string $chatId): ?GameSnapshot
    {
        $gid = $this->byChat[$chatId] ?? null;

        return $gid === null ? null : $this->snapshots[$gid];
    }

    public function gameByUser(string $userId): ?GameSnapshot
    {
        $gid = $this->byUser[$userId] ?? null;

        return $gid === null ? null : $this->snapshots[$gid];
    }

    public function activeGames(): array
    {
        return array_values(array_filter(
            $this->snapshots,
            fn (GameSnapshot $s) => $s->phase !== PhaseEnum::Ended
        ));
    }

    public function consumeSayLock(string $userId, string $gameId): bool
    {
        $key = $userId.':'.$gameId;
        if (($this->sayLocks[$key] ?? null) === $gameId) {
            unset($this->sayLocks[$key]);

            return true;
        }

        return false;
    }

    public function setSayLock(string $userId, string $gameId): void
    {
        $this->sayLocks[$userId.':'.$gameId] = $gameId;
    }

    public function dmConfirmations(string $roomId, array $userIds): array
    {
        $out = [];
        foreach ($userIds as $u) {
            $out[$u] = $this->dmConfirmed[$roomId][$u] ?? false;
        }

        return $out;
    }

    public function setDmConfirmed(string $roomId, string $userId): void
    {
        $this->dmConfirmed[$roomId][$userId] = true;
    }
}
