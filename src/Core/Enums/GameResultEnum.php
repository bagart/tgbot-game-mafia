<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core\Enums;

enum GameResultEnum: string
{
    case TownWon = 'town_won';
    case MafiaWon = 'mafia_won';
    case SoloWon = 'solo_won';
    case SatanistWon = 'satanist_won';
    case Cancelled = 'cancelled';
}
