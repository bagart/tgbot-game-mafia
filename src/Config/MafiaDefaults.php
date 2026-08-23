<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Config;

/**
 * Tunable defaults (room templates override per room; per-chat module_settings
 * override per bot/chat). Single definition site — no magic numbers elsewhere.
 */
final class MafiaDefaults
{
    public const NIGHT_SECONDS = 75;

    public const DISCUSSION_SECONDS = 150;

    public const VOTE_SECONDS = 45;

    public const PLAYERS_MIN = 5;

    public const PLAYERS_MAX = 15;

    public const MAX_BOTS_DEFAULT = 4;

    /** Discipline: 2 consecutive skips => freeze. */
    public const SKIP_STRIKES_TO_FREEZE = 2;

    public const FREEZE_MINUTES = 15;

    public const SPEECH_COOLDOWN_SECONDS = 10;

    public const EMERGENCY_PER_PLAYER = 1;

    public const EMERGENCY_PER_GAME = 2;
}
