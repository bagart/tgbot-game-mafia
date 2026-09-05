<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\I18n\LocaleResolver;

it('prefers the profile locale over every other level (ONB-1)', function () {
    expect((new LocaleResolver())->resolve('ru', 'es', 'en', 'zh'))->toBe('ru');
});

it('falls back to room locale when profile is null or invalid', function () {
    $resolver = new LocaleResolver();

    expect($resolver->resolve(null, 'es', 'en'))->toBe('es')
        ->and($resolver->resolve('de', 'es'))->toBe('es');
});

it('falls back to chat locale when profile and room are empty', function () {
    expect((new LocaleResolver())->resolve(null, null, 'zh'))->toBe('zh');
});

it('falls back to bot default when higher levels are null', function () {
    expect((new LocaleResolver())->resolve(null, null, null, 'zh'))->toBe('zh');
});

it('returns en when every level is empty or invalid', function () {
    $resolver = new LocaleResolver();

    expect($resolver->resolve(null, null, null, null))->toBe('en')
        ->and($resolver->resolve('', '', '', ''))->toBe('en')
        ->and($resolver->resolve('de', 'klingon', 'xx', 'yy'))->toBe('en');
});

it('skips invalid values but keeps later valid levels', function () {
    $resolver = new LocaleResolver();

    expect($resolver->resolve('', 'de', 'ru'))->toBe('ru')
        ->and($resolver->resolve(null, '', 'zz', 'es'))->toBe('es');
});

it('validates locales against the allowed set', function () {
    expect(LocaleResolver::isValid('en'))->toBeTrue()
        ->and(LocaleResolver::isValid('ru'))->toBeTrue()
        ->and(LocaleResolver::isValid('es'))->toBeTrue()
        ->and(LocaleResolver::isValid('zh'))->toBeTrue()
        ->and(LocaleResolver::isValid('EN'))->toBeFalse()
        ->and(LocaleResolver::isValid('fr'))->toBeFalse()
        ->and(LocaleResolver::isValid(''))->toBeFalse();
});
