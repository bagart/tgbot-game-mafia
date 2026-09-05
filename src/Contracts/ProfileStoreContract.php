<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Contracts;

/**
 * Per-user discipline profile storage (skips, freeze, sleepy totals, stats).
 */
interface ProfileStoreContract
{
    public function skips(string $userId): int;

    public function recordSkip(string $userId): int;

    public function resetSkips(string $userId): void;

    public function frozenUntil(string $userId): ?int;

    public function freeze(string $userId, int $untilEpoch): void;

    public function addSleepy(string $userId): void;

    public function sleepyTotal(string $userId): int;

    public function recordGame(string $userId, string $role, bool $won): void;

    public function hasFlag(string $userId, string $flag): bool;

    public function setFlag(string $userId, string $flag): void;

    /** ONB-1: user-chosen interface locale, null when never set. */
    public function preferredLocale(string $userId): ?string;

    public function setPreferredLocale(string $userId, string $locale): void;
}
