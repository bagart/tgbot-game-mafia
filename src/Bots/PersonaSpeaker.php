<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Bots;

use BAGArt\TelegramBotMafia\I18n\LangPack;

/**
 * Renders filler-player speech from the persona packs. Seeded picks keep bot
 * chatter reproducible in tests.
 */
final class PersonaSpeaker
{
    private \Closure $random;

    public function __construct(
        private readonly LangPack $pack,
        ?\Closure $random = null,
    ) {
        $this->random = $random ?? static fn (int $max): int => random_int(0, $max);
    }

    /** @param  array<string, string>  $replace */
    public function line(string $category, array $replace = []): string
    {
        return $this->pack->line($category, $replace, ($this->random)(99));
    }
}
