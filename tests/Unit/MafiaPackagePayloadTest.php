<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Console\BotPackaging;

it('builds the four user-facing commands with short descriptions', function () {
    foreach (['en', 'ru'] as $locale) {
        $commands = BotPackaging::commands($locale);

        expect(array_column($commands, 'command'))->toBe(['play', 'kick', 'rules', 'roles']);

        foreach ($commands as $entry) {
            expect($entry['description'])->not->toBe('')
                ->and(mb_strlen($entry['description']))->toBeLessThanOrEqual(40);
        }
    }
});

it('returns no commands for an unknown locale', function () {
    expect(BotPackaging::commands('de'))->toBe([])
        ->and(BotPackaging::commands(null))->toBe([]);
});

it('keeps localized profile descriptions within Telegram limits for all locales', function () {
    expect(BotPackaging::supportedLocales())->toBe(['en', 'ru', 'es', 'zh']);

    foreach (BotPackaging::supportedLocales() as $locale) {
        $texts = BotPackaging::profileDescriptions($locale);

        expect($texts)->not->toBeNull()
            ->and(mb_strlen($texts['short']))->toBeGreaterThan(0)
            ->and(mb_strlen($texts['short']))->toBeLessThanOrEqual(120)
            ->and(mb_strlen($texts['long']))->toBeGreaterThan(0)
            ->and(mb_strlen($texts['long']))->toBeLessThanOrEqual(512);
    }
});

it('filters unsupported locales and falls back to defaults on empty input', function () {
    expect(BotPackaging::resolveLocales([]))->toBe(['en', 'ru'])
        ->and(BotPackaging::resolveLocales(['de']))->toBe(['en', 'ru'])
        ->and(BotPackaging::resolveLocales(['DE,en', ' ru ', 'es']))->toBe(['en', 'ru', 'es'])
        ->and(BotPackaging::resolveLocales(['es']))->toBe(['es'])
        ->and(BotPackaging::profileDescriptions('de'))->toBeNull();
});
