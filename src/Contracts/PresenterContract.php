<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Contracts;

use BAGArt\TelegramBotMafia\Core\GameSnapshot;
use BAGArt\TelegramBotMafia\Core\NightReport;
use BAGArt\TelegramBotMafia\Core\VoteOutcome;
use BAGArt\TelegramBotMafia\Presentation\SendPlan;

/**
 * Renderer seam between the pure GameCore and Telegram skins (group chat vs
 * private interface). Presenters are PURE: they translate game events into
 * SendPlans; the coordinator executes delivery through TgSenderContract.
 */
interface PresenterContract
{
    /** @return list<SendPlan> */
    public function phaseAnnounce(GameSnapshot $snapshot): array;

    /** @return list<SendPlan> */
    public function roleDealt(GameSnapshot $snapshot, int $seat): array;

    /** @return list<SendPlan> */
    public function morning(GameSnapshot $snapshot, NightReport $report): array;

    /** @return list<SendPlan> */
    public function voteClosed(GameSnapshot $snapshot, VoteOutcome $outcome): array;

    /** @return list<SendPlan> */
    public function gameEnded(GameSnapshot $snapshot): array;
}
