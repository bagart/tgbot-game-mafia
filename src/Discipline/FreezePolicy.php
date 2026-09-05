<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Discipline;

use BAGArt\TelegramBotMafia\Config\MafiaDefaults;
use BAGArt\TelegramBotMafia\Contracts\ClockContract;
use BAGArt\TelegramBotMafia\Contracts\ProfileStoreContract;

/**
 * Skip-strike discipline: N consecutive skips => participation freeze.
 * A fully played game resets the counter. Pure policy over the store.
 */
final readonly class FreezePolicy
{
    public function __construct(
        private ProfileStoreContract $profiles,
        private ClockContract $clock,
    ) {
    }

    /** @return int|null frozen-until epoch when the freeze triggers, null otherwise */
    public function registerSkip(string $userId): ?int
    {
        $skips = $this->profiles->recordSkip($userId);
        if ($skips < MafiaDefaults::SKIP_STRIKES_TO_FREEZE) {
            return null;
        }
        $until = $this->clock->now() + MafiaDefaults::FREEZE_MINUTES * 60;
        $this->profiles->freeze($userId, $until);
        $this->profiles->resetSkips($userId);

        return $until;
    }

    public function registerParticipation(string $userId): void
    {
        $this->profiles->resetSkips($userId);
    }

    public function isFrozen(string $userId): bool
    {
        $until = $this->profiles->frozenUntil($userId);

        return $until !== null && $until > $this->clock->now();
    }
}
