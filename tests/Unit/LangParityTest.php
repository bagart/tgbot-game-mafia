<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\I18n\LangPack;

const PROD_LANG_DIR = __DIR__.'/../../resources/lang';

it('has identical ui key sets across all four locales', function () {
    $keys = [];
    foreach (['en', 'ru', 'es', 'zh'] as $locale) {
        $keys[$locale] = (new LangPack($locale, PROD_LANG_DIR))->uiKeys();
        sort($keys[$locale]);
    }

    foreach (['ru', 'es', 'zh'] as $locale) {
        $missing = array_diff($keys['en'], $keys[$locale]);
        $extra = array_diff($keys[$locale], $keys['en']);

        expect($missing)->toBe([])
            ->and($extra)->toBe([]);
    }

    expect($keys['ru'])->toBe($keys['en'])
        ->and($keys['es'])->toBe($keys['en'])
        ->and($keys['zh'])->toBe($keys['en']);
});
