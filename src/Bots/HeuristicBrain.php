<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Bots;

use BAGArt\TelegramBotMafia\Contracts\BotBrainContract;
use BAGArt\TelegramBotMafia\Core\PublicStateView;
use BAGArt\TelegramBotMafia\Core\RoleCatalog;

/**
 * MVP heuristic brain. Fairness firewall: decides ONLY from the filtered
 * views — public roster + own private slice. Seeded-random choice keeps
 * games reproducible; suspicion scoring arrives with personality profiles.
 */
final class HeuristicBrain implements BotBrainContract
{
    private \Closure $random;

    /** @param  \Closure(int): int $random max-inclusive RNG */
    public function __construct(
        ?\Closure $random = null,
    ) {
        $this->random = $random ?? static fn (int $max): int => random_int(0, $max);
    }

    public function chooseNightTarget(PublicStateView $view, int $actorSeat, string $role): ?int
    {
        $targets = [];
        foreach ($view->seats as $seat) {
            if (! $seat->alive || $seat->seat === $actorSeat) {
                continue;
            }
            // mafia bloc never shoots its own team
            if (in_array($role, RoleCatalog::mafiaTeamIds(), true)
                && in_array($seat->seat, $view->teammateSeats, true)
            ) {
                continue;
            }
            $targets[] = $seat->seat;
        }
        if ($targets === []) {
            return null;
        }

        return $targets[($this->random)(count($targets) - 1)];
    }

    public function chooseVote(PublicStateView $view, int $actorSeat): ?int
    {
        $targets = [];
        foreach ($view->seats as $seat) {
            if ($seat->alive && $seat->seat !== $actorSeat) {
                $targets[] = $seat->seat;
            }
        }
        if ($targets === []) {
            return null;
        }

        return $targets[($this->random)(count($targets) - 1)];
    }
}
