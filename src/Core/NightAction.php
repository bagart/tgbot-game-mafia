<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core;

/**
 * One submitted night action. `targetSeat = null` means explicit skip.
 * Types follow roles.json actions: heal, block_action, protect, kill,
 * check_alignment, check_exact_role.
 */
final readonly class NightAction
{
    public const SKIP = 'skip';

    public function __construct(
        public int $actorSeat,
        public string $type,
        public ?int $targetSeat,
    ) {
    }
}
