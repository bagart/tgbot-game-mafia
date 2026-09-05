<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core\Enums;

/**
 * Pencil-mark kinds a player can pin to a seat in their private notes pad.
 * The store treats these as opaque values; meaning (and engine-written auto
 * marks such as check results) lives elsewhere.
 */
enum MarkKind: string
{
    case Suspect = 'suspect';
    case Clear = 'clear';
    case Doubt = 'doubt';
    case VoteTarget = 'vote_target';
}
