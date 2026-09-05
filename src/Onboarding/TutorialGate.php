<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Onboarding;

use BAGArt\TelegramBotMafia\Contracts\ProfileStoreContract;

/**
 * First-run tutorial gate: the welcome DM is sent once per user, keyed by
 * the persistent 'tutorial_seen' flag. The caller wires: shouldSendTutorial()
 -> send welcome -> markTutorialSeen().
 */
final class TutorialGate
{
    public const FLAG = 'tutorial_seen';

    public function __construct(
        private readonly ProfileStoreContract $profiles,
    ) {
    }

    public function shouldSendTutorial(string $userId): bool
    {
        return ! $this->profiles->hasFlag($userId, self::FLAG);
    }

    public function markTutorialSeen(string $userId): void
    {
        $this->profiles->setFlag($userId, self::FLAG);
    }
}
