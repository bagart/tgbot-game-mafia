<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\ProfileStoreContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * Eloquent-backed profile store. Replaces InMemoryProfileStore for
 * production — user discipline profiles persist across restarts.
 *
 * Schema: mafia_profiles (bot_id, user_id, consecutive_skips, frozen_until,
 * sleepy_total, games_played, wins, favorite_role, flags, preferred_locale).
 *
 * Flags and preferred_locale are stored in a JSON column to avoid
 * additional tables for simple key-value data.
 */
final class EloquentProfileStore implements ProfileStoreContract
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'mafia_profiles',
    ) {
    }

    public function skips(string $userId): int
    {
        return (int) ($this->getOrCreate($userId)->consecutive_skips ?? 0);
    }

    public function recordSkip(string $userId): int
    {
        $row = $this->getOrCreate($userId);
        $newSkips = ((int) $row->consecutive_skips) + 1;

        $this->connection->table($this->table)
            ->where('user_id', $userId)
            ->update(['consecutive_skips' => $newSkips, 'updated_at' => now()]);

        return $newSkips;
    }

    public function resetSkips(string $userId): void
    {
        $this->getOrCreate($userId);

        $this->connection->table($this->table)
            ->where('user_id', $userId)
            ->update(['consecutive_skips' => 0, 'updated_at' => now()]);
    }

    public function frozenUntil(string $userId): ?int
    {
        $row = $this->getOrCreate($userId);

        return $row->frozen_until !== null ? strtotime((string) $row->frozen_until) : null;
    }

    public function freeze(string $userId, int $untilEpoch): void
    {
        $this->getOrCreate($userId);

        $this->connection->table($this->table)
            ->where('user_id', $userId)
            ->update([
                'frozen_until' => date('Y-m-d H:i:s', $untilEpoch),
                'updated_at' => now(),
            ]);
    }

    public function addSleepy(string $userId): void
    {
        $row = $this->getOrCreate($userId);
        $newTotal = ((int) $row->sleepy_total) + 1;

        $this->connection->table($this->table)
            ->where('user_id', $userId)
            ->update(['sleepy_total' => $newTotal, 'updated_at' => now()]);
    }

    public function sleepyTotal(string $userId): int
    {
        return (int) ($this->getOrCreate($userId)->sleepy_total ?? 0);
    }

    public function recordGame(string $userId, string $role, bool $won): void
    {
        $row = $this->getOrCreate($userId);
        $gamesPlayed = ((int) $row->games_played) + 1;
        $wins = ((int) $row->wins) + ($won ? 1 : 0);

        $this->connection->table($this->table)
            ->where('user_id', $userId)
            ->update([
                'games_played' => $gamesPlayed,
                'wins' => $wins,
                'favorite_role' => $role,
                'updated_at' => now(),
            ]);
    }

    public function hasFlag(string $userId, string $flag): bool
    {
        $row = $this->getOrCreate($userId);
        $flags = is_array($row->flags) ? $row->flags : [];

        return isset($flags[$flag]);
    }

    public function setFlag(string $userId, string $flag): void
    {
        $row = $this->getOrCreate($userId);
        $flags = is_array($row->flags) ? $row->flags : [];
        $flags[$flag] = true;

        $this->connection->table($this->table)
            ->where('user_id', $userId)
            ->update(['flags' => $flags, 'updated_at' => now()]);
    }

    public function preferredLocale(string $userId): ?string
    {
        $row = $this->getOrCreate($userId);

        return $row->preferred_locale !== null ? (string) $row->preferred_locale : null;
    }

    public function setPreferredLocale(string $userId, string $locale): void
    {
        $this->getOrCreate($userId);

        $this->connection->table($this->table)
            ->where('user_id', $userId)
            ->update(['preferred_locale' => $locale, 'updated_at' => now()]);
    }

    private function getOrCreate(string $userId): object
    {
        $row = $this->connection->table($this->table)
            ->where('user_id', $userId)
            ->first();

        if ($row !== null) {
            return $row;
        }

        $id = (string) Str::uuid();
        $now = now();

        $this->connection->table($this->table)->insert([
            'id' => $id,
            'bot_id' => 'default',
            'user_id' => $userId,
            'consecutive_skips' => 0,
            'sleepy_total' => 0,
            'games_played' => 0,
            'wins' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->connection->table($this->table)
            ->where('user_id', $userId)
            ->first();
    }
}
