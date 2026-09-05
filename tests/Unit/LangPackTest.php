<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\I18n\LangPack;

const FIXTURE_LANG_DIR = __DIR__.'/../Fixtures/lang';

it('escapes hostile user data by default', function () {
    $pack = new LangPack('en', FIXTURE_LANG_DIR);
    $hostile = '<b onclick="x">Name & Co</b>';

    $line = $pack->t('test.hello', ['name' => $hostile]);

    expect($line)->toBe('Hello, &lt;b onclick=&quot;x&quot;&gt;Name &amp; Co&lt;/b&gt;!');
});

it('passes raw user data through when escaping is disabled', function () {
    $pack = new LangPack('en', FIXTURE_LANG_DIR);
    $hostile = '<b onclick="x">Name & Co</b>';

    $line = $pack->t('test.hello', ['name' => $hostile], null, false);

    expect($line)->toBe('Hello, <b onclick="x">Name & Co</b>!');
});

it('maps russian counts to CLDR plural categories', function () {
    $pack = new LangPack('ru', FIXTURE_LANG_DIR);

    expect($pack->t('test.plural', count: 1))->toBe('ОДИН предмет')
        ->and($pack->t('test.plural', count: 2))->toBe('НЕСКОЛЬКО предметов')
        ->and($pack->t('test.plural', count: 5))->toBe('МНОГО предметов')
        ->and($pack->t('test.plural', count: 11))->toBe('МНОГО предметов')
        ->and($pack->t('test.plural', count: 21))->toBe('ОДИН предмет');
});

it('maps english counts to one/other only', function () {
    $pack = new LangPack('en', FIXTURE_LANG_DIR);

    expect($pack->t('test.plural', count: 1))->toBe('ONE item')
        ->and($pack->t('test.plural', count: 2))->toBe('OTHER items')
        ->and($pack->t('test.plural', count: 21))->toBe('OTHER items');
});

it('always uses the other category for chinese', function () {
    $pack = new LangPack('zh', FIXTURE_LANG_DIR);

    expect($pack->t('test.plural', count: 1))->toBe('其他物品')
        ->and($pack->t('test.plural', count: 5))->toBe('其他物品')
        ->and($pack->t('test.plural', count: 100))->toBe('其他物品');
});
