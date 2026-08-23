<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\I18n\LangPack;

const LANG_DIR = __DIR__.'/../../resources/lang';

it('has identical key sets across all four locales', function () {
    $keys = [];
    foreach (['ru', 'en', 'zh', 'es'] as $locale) {
        $pack = new LangPack($locale, LANG_DIR);
        $keys[$locale] = $pack->uiKeys();
        sort($keys[$locale]);
    }

    expect(count($keys['ru']))->toBeGreaterThan(250)
        ->and($keys['en'])->toBe($keys['ru'])
        ->and($keys['zh'])->toBe($keys['ru'])
        ->and($keys['es'])->toBe($keys['ru']);
});

it('interpolates placeholders and escapes user data', function () {
    $pack = new LangPack('en', LANG_DIR);

    $line = $pack->t('day.last_words_broadcast', ['name' => '<b>Eve</b>', 'words' => 'gg']);
    expect($line)->toContain('&lt;b&gt;Eve&lt;/b&gt;')
        ->and($line)->toContain('“gg”');
});

it('applies russian plural categories', function () {
    $pack = new LangPack('ru', LANG_DIR);

    // synthetic plural object through the public API: use a raw key injection
    // via reflection-free path — pluralLine is private, so assert via t() on
    // a known plural-less key plus direct category math instead.
    $one = $pack->t('common.seconds_left', ['seconds' => 21]);
    expect($one)->toBeString();

    // ruCategory behaviour exercised indirectly: 1 -> one, 2 -> few, 5 -> many, 11 -> many
    $method = new ReflectionMethod(LangPack::class, 'pluralLine');
    $method->setAccessible(true);
    $variants = ['one' => 'ONE', 'few' => 'FEW', 'many' => 'MANY', 'other' => 'OTHER'];
    expect($method->invoke($pack, $variants, 1))->toBe('ONE')
        ->and($method->invoke($pack, $variants, 2))->toBe('FEW')
        ->and($method->invoke($pack, $variants, 5))->toBe('MANY')
        ->and($method->invoke($pack, $variants, 11))->toBe('MANY')
        ->and($method->invoke($pack, $variants, 21))->toBe('ONE');

    // zh has only "other"
    $zh = new ReflectionMethod(new LangPack('zh', LANG_DIR), 'pluralLine');
    $zh->setAccessible(true);
    expect($zh->invoke(new LangPack('zh', LANG_DIR), $variants, 1))->toBe('OTHER');
});
