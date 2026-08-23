<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\ProfileStoreContract;

/**
 * In-memory profiles (tests / pure-PHP). Production swaps a DB-backed
 * implementation (mafia_profiles table) behind the same contract.
 */
final class InMemoryProfileStore implements ProfileStoreContract
{
    /** @var array<string, array{skips:int,frozen:?int,sleepy:int}> */
    private array $profiles = [];

    private function ensure(string $userId): void
    {
        $this->profiles[$userId] ??= ['skips' => 0, 'frozen' => null, 'sleepy' => 0];
    }

    public function skips(string $userId): int
    {
        $this->ensure($userId);

        return $this->profiles[$userId]['skips'];
    }

    public function recordSkip(string $userId): int
    {
        $this->ensure($userId);

        return ++$this->profiles[$userId]['skips'];
    }

    public function resetSkips(string $userId): void
    {
        $this->ensure($userId);
        $this->profiles[$userId]['skips'] = 0;
    }

    public function frozenUntil(string $userId): ?int
    {
        $this->ensure($userId);

        return $this->profiles[$userId]['frozen'];
    }

    public function freeze(string $userId, int $untilEpoch): void
    {
        $this->ensure($userId);
        $this->profiles[$userId]['frozen'] = $untilEpoch;
    }

    public function addSleepy(string $userId): void
    {
        $this->ensure($userId);
        $this->profiles[$userId]['sleepy']++;
    }

    public function sleepyTotal(string $userId): int
    {
        $this->ensure($userId);

        return $this->profiles[$userId]['sleepy'];
    }

    public function recordGame(string $userId, string $role, bool $won): void
    {
        // stats aggregation lands with the DB-backed store
    }
}
