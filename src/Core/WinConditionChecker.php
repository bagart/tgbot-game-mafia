<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core;

use BAGArt\TelegramBotMafia\Core\Enums\GameResultEnum;

/**
 * Win-condition evaluation in roles.json engine order:
 * satanist sacrifice → mafia parity → all killers dead → solo last standing.
 */
final class WinConditionChecker
{
    public function evaluate(GameSnapshot $snapshot, bool $satanistSacrificed = false): ?GameResultEnum
    {
        if ($satanistSacrificed) {
            return GameResultEnum::SatanistWon;
        }

        $alive = $snapshot->aliveSeats();
        if ($alive === []) {
            return null;
        }

        $mafia = 0;
        $killers = 0;
        foreach ($alive as $s) {
            $role = (string) $s->role;
            if (in_array($role, RoleCatalog::mafiaTeamIds(), true)) {
                $mafia++;
            }
            if (RoleCatalog::isKillerRole($role)) {
                $killers++;
            }
        }
        $others = count($alive) - $mafia;

        if ($mafia > 0 && $mafia >= $others) {
            return GameResultEnum::MafiaWon;
        }

        if ($killers === 0) {
            return GameResultEnum::TownWon;
        }

        // solo last standing: the only alive player is a solo killer
        if (count($alive) === 1 && ! in_array((string) $alive[0]->role, RoleCatalog::mafiaTeamIds(), true)
            && RoleCatalog::isKillerRole((string) $alive[0]->role)
        ) {
            return GameResultEnum::SoloWon;
        }

        return null;
    }
}
