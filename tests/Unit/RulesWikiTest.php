<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Contracts\ProfileStoreContract;
use BAGArt\TelegramBotMafia\I18n\LangPack;
use BAGArt\TelegramBotMafia\Onboarding\RulesWiki;
use BAGArt\TelegramBotMafia\Onboarding\TutorialGate;
use BAGArt\TelegramBotMafia\State\InMemoryProfileStore;

const WIKI_LANG_DIR = __DIR__.'/../../resources/lang';
const LOCALES = ['en', 'ru', 'es', 'zh'];

function wikiLangPack(string $locale): LangPack
{
    return new LangPack($locale, WIKI_LANG_DIR);
}

it('renders all pages with unique titles in en and ru', function (): void {
    foreach (['en', 'ru'] as $locale) {
        $wiki = new RulesWiki(wikiLangPack($locale), '-100');
        $titles = [];
        for ($i = 0; $i < $wiki->pageCount(); $i++) {
            $plan = $wiki->page($i);
            expect($plan->text)->not->toBe('');
            expect(mb_strlen($plan->text))->toBeGreaterThan(100);
            $line = explode("\n", $plan->text)[2];
            expect($line)->not->toBe('<b></b>');
            $titles[] = $line;
        }
        expect(count(array_unique($titles)))->toBe($wiki->pageCount());
    }
});

it('shows the page counter chip on the first page', function (): void {
    $wiki = new RulesWiki(wikiLangPack('en'), '-100');
    expect($wiki->firstPage()->text)->toContain('1/6');
});

it('navigates from page 0 to neighbours with wrap-around', function (): void {
    $wiki = new RulesWiki(wikiLangPack('en'), '-100');
    $callbacks = [];
    foreach ($wiki->firstPage()->keyboard ?? [] as $row) {
        foreach ($row as $button) {
            $callbacks[] = $button['callback'];
        }
    }
    expect($callbacks)->toContain('m:rules:1')
        ->and($callbacks)->toContain('m:rules:5')
        ->and($callbacks)->not->toContain('m:rolepage:civilian');
});

it('wraps negative and overflowing indices into range', function (): void {
    $wiki = new RulesWiki(wikiLangPack('en'), '-100');
    expect($wiki->page(-1)->text)->toBe($wiki->page(5)->text)
        ->and($wiki->page(6)->text)->toBe($wiki->firstPage()->text);
});

it('links the role encyclopedia on the last page only', function (): void {
    $wiki = new RulesWiki(wikiLangPack('en'), '-100');
    $last = $wiki->page($wiki->pageCount() - 1);
    $all = [];
    foreach ($last->keyboard ?? [] as $row) {
        foreach ($row as $button) {
            $all[] = $button['callback'];
        }
    }
    expect($all)->toContain('m:rolepage:civilian');

    for ($i = 0; $i < $wiki->pageCount() - 1; $i++) {
        $flat = json_encode($wiki->page($i)->keyboard, JSON_THROW_ON_ERROR);
        expect($flat)->not->toContain('m:rolepage');
    }
});

it('fires the tutorial exactly once per user', function (): void {
    $profiles = new InMemoryProfileStore();
    $gate = new TutorialGate($profiles);

    expect($gate->shouldSendTutorial('u1'))->toBeTrue();
    $gate->markTutorialSeen('u1');

    expect($gate->shouldSendTutorial('u1'))->toBeFalse()
        ->and($profiles instanceof ProfileStoreContract)->toBeTrue();
});

it('stays silent after mark without a prior check', function (): void {
    $gate = new TutorialGate(new InMemoryProfileStore());
    $gate->markTutorialSeen('u9');

    expect($gate->shouldSendTutorial('u9'))->toBeFalse();
});

it('tracks users independently', function (): void {
    $gate = new TutorialGate(new InMemoryProfileStore());
    $gate->markTutorialSeen('a');

    expect($gate->shouldSendTutorial('a'))->toBeFalse()
        ->and($gate->shouldSendTutorial('b'))->toBeTrue();
});
