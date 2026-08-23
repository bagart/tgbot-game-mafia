<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core\Enums;

enum PhaseEnum: string
{
    case Setup = 'setup';
    case Night = 'night';
    case DayDiscussion = 'day_discussion';
    case DayVoting = 'day_voting';
    case Ended = 'ended';

    public function next(): self
    {
        return match ($this) {
            PhaseEnum::Setup => PhaseEnum::Night,
            PhaseEnum::Night => PhaseEnum::DayDiscussion,
            PhaseEnum::DayDiscussion => PhaseEnum::DayVoting,
            // leaving voting is resolver-driven (revote loop or next night)
            PhaseEnum::DayVoting, PhaseEnum::Ended => PhaseEnum::Ended,
        };
    }
}
