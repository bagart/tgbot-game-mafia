<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Contracts;

use BAGArt\TelegramBotMafia\Core\PublicStateView;

/**
 * Decision seam for AI filler players. CRITICAL: implementations receive
 * filtered views only — never the full snapshot. This is the fairness
 * firewall; an arch test guards that brains do not touch GameSnapshot.
 */
interface BotBrainContract
{
    /** @return int|null seat to act on, or null for "skip" */
    public function chooseNightTarget(PublicStateView $view, int $actorSeat, string $role): ?int;

    /** @return int|null seat to vote for, or null to abstain */
    public function chooseVote(PublicStateView $view, int $actorSeat): ?int;
}
