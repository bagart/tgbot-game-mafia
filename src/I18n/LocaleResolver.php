<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\I18n;

/**
 * Strict locale fallback chain: profile preference → room → chat → bot default → 'en'.
 * Invalid or empty values fall through to the next level.
 */
final class LocaleResolver
{
    public const ALLOWED = ['en', 'ru', 'es', 'zh'];

    private const FALLBACK = 'en';

    public function resolve(?string $profileLocale, ?string $roomLocale, ?string $chatLocale = null, ?string $botDefault = null): string
    {
        foreach ([$profileLocale, $roomLocale, $chatLocale, $botDefault] as $candidate) {
            if (is_string($candidate) && self::isValid($candidate)) {
                return $candidate;
            }
        }

        return self::FALLBACK;
    }

    public static function isValid(string $locale): bool
    {
        return in_array($locale, self::ALLOWED, true);
    }
}
