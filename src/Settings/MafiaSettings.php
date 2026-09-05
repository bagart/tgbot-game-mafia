<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Settings;

use BAGArt\TelegramBotMafia\Config\MafiaDefaults;

/**
 * Effective mafia settings resolved from ModuleSettingsContract (chat-level
 * row over platform defaults). All clamping happens here — nothing downstream
 * re-validates. Not persisted directly; the raw map lives in
 * tg_module_enablements.module_settings.
 */
final readonly class MafiaSettings
{
    public const BALLOT_OPEN = 'open';

    public const BALLOT_SECRET = 'secret';

    public const NIGHT_MIN = 15;

    public const NIGHT_MAX = 600;

    public const DISCUSSION_MIN = 30;

    public const DISCUSSION_MAX = 1800;

    public const VOTE_MIN = 15;

    public const VOTE_MAX = 600;

    /** @var list<string> locales shipped in resources/lang */
    public const LOCALES = ['ru', 'en', 'es', 'zh'];

    /** GRP-10 room templates: id → raw settings map (same keys as fromArray) */
    public const TEMPLATES = [
        'classic' => [],
        'blitz' => ['night_seconds' => 30, 'discussion_seconds' => 60, 'vote_seconds' => 20],
        'tournament' => ['night_seconds' => 90, 'discussion_seconds' => 240, 'vote_seconds' => 60, 'max_bots' => 0],
    ];

    public static function template(string $id): ?self
    {
        if (! isset(self::TEMPLATES[$id])) {
            return null;
        }

        return self::fromArray(self::TEMPLATES[$id]);
    }

    public function __construct(
        public int $nightSeconds = MafiaDefaults::NIGHT_SECONDS,
        public int $discussionSeconds = MafiaDefaults::DISCUSSION_SECONDS,
        public int $voteSeconds = MafiaDefaults::VOTE_SECONDS,
        public string $locale = 'ru',
        public string $ballotMode = self::BALLOT_OPEN,
        public int $maxBots = MafiaDefaults::MAX_BOTS_DEFAULT,
        public int $playersMin = MafiaDefaults::PLAYERS_MIN,
        public int $playersMax = MafiaDefaults::PLAYERS_MAX,
        /** GRP-11: randomize seat assignment at deal */
        public bool $shuffleSeats = false,
        /** BOT-6: mid-game leaver's seat is taken over by a fresh bot */
        public bool $replaceLeavers = true,
        /** OPS-4 kill-switches — consumed from W5 on; risky features default OFF */
        public bool $willsEnabled = false,
        public bool $reactionsEnabled = false,
        public bool $phasePingsEnabled = false,
        public bool $ghostPredictionsEnabled = false,
        public bool $pencilMarksEnabled = false,
        public bool $webAppButtonsEnabled = false,
        public bool $mirrorEnabled = true,
        public bool $speakingRelayEnabled = true,
    ) {
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $min = self::clampInt($raw['players_min'] ?? null, MafiaDefaults::PLAYERS_MIN, MafiaDefaults::PLAYERS_MIN, MafiaDefaults::PLAYERS_MAX);
        $max = self::clampInt($raw['players_max'] ?? null, MafiaDefaults::PLAYERS_MAX, $min, MafiaDefaults::PLAYERS_MAX);

        return new self(
            nightSeconds: self::clampInt($raw['night_seconds'] ?? null, MafiaDefaults::NIGHT_SECONDS, self::NIGHT_MIN, self::NIGHT_MAX),
            discussionSeconds: self::clampInt($raw['discussion_seconds'] ?? null, MafiaDefaults::DISCUSSION_SECONDS, self::DISCUSSION_MIN, self::DISCUSSION_MAX),
            voteSeconds: self::clampInt($raw['vote_seconds'] ?? null, MafiaDefaults::VOTE_SECONDS, self::VOTE_MIN, self::VOTE_MAX),
            locale: self::locale($raw['locale'] ?? null),
            ballotMode: self::ballotMode($raw['ballot_mode'] ?? null),
            maxBots: self::clampInt($raw['max_bots'] ?? null, MafiaDefaults::MAX_BOTS_DEFAULT, 0, MafiaDefaults::PLAYERS_MAX - 1),
            playersMin: $min,
            playersMax: $max,
            shuffleSeats: (bool) ($raw['shuffle_seats'] ?? false),
            replaceLeavers: ! isset($raw['replace_leavers']) || (bool) $raw['replace_leavers'],
            willsEnabled: self::flag($raw['wills_enabled'] ?? null, false),
            reactionsEnabled: self::flag($raw['reactions_enabled'] ?? null, false),
            phasePingsEnabled: self::flag($raw['phase_pings_enabled'] ?? null, false),
            ghostPredictionsEnabled: self::flag($raw['ghost_predictions_enabled'] ?? null, false),
            pencilMarksEnabled: self::flag($raw['pencil_marks_enabled'] ?? null, false),
            webAppButtonsEnabled: self::flag($raw['web_app_buttons_enabled'] ?? null, false),
            mirrorEnabled: self::flag($raw['mirror_enabled'] ?? null, true),
            speakingRelayEnabled: self::flag($raw['speaking_relay_enabled'] ?? null, true),
        );
    }

    private static function clampInt(mixed $value, int $default, int $lo, int $hi): int
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            return $default;
        }
        $n = (int) $value;

        return max($lo, min($hi, $n));
    }

    private static function locale(mixed $value): string
    {
        return is_string($value) && in_array($value, self::LOCALES, true) ? $value : 'ru';
    }

    private static function ballotMode(mixed $value): string
    {
        return $value === self::BALLOT_SECRET ? self::BALLOT_SECRET : self::BALLOT_OPEN;
    }

    private static function flag(mixed $value, bool $default): bool
    {
        return is_scalar($value) ? (bool) $value : $default;
    }
}
