<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\I18n\LangPack;
use BAGArt\TelegramBotMafia\Presentation\RoleEncyclopedia;

const LANG_DIR = __DIR__.'/../../resources/lang';
const ROLES_JSON = __DIR__.'/../../resources/roles.json';

function encyclopediaRoles(): array
{
    return array_keys(json_decode((string) file_get_contents(ROLES_JSON), true, 512, JSON_THROW_ON_ERROR)['roles']);
}

function encyclopediaLocales(): array
{
    return ['en', 'ru', 'es', 'zh'];
}

function encyclopediaCallbacks(?array $keyboard): array
{
    $out = [];
    foreach ((array) $keyboard as $row) {
        foreach ($row as $button) {
            $out[] = $button['callback'];
        }
    }

    return $out;
}

it('renders a page with name and emoji for every role in every locale', function () {
    foreach (encyclopediaLocales() as $locale) {
        $lang = new LangPack($locale, LANG_DIR);
        $book = new RoleEncyclopedia($lang);
        foreach (encyclopediaRoles() as $roleId) {
            $plan = $book->page($roleId);
            expect($plan->text)->toContain($lang->t('roles.'.$roleId.'.name'))
                ->and($plan->text)->toContain($lang->t('roles.'.$roleId.'.emoji'));
        }
    }
});

it('cycles prev/next across the fixed role order', function () {
    $book = new RoleEncyclopedia(new LangPack('en', LANG_DIR));
    $ids = encyclopediaRoles();
    $first = $ids[0];
    $last = $ids[count($ids) - 1];

    $nextOfLast = null;
    foreach (encyclopediaCallbacks($book->page($last)->keyboard) as $callback) {
        if (str_starts_with($callback, 'm:rolepage:')) {
            $parts = explode(':', $callback);
            if ($parts[2] !== $first) {
                continue;
            }
            $nextOfLast = $parts[2];
        }
    }
    expect($nextOfLast)->toBe($first);

    $prevSeen = [];
    foreach (encyclopediaCallbacks($book->page($first)->keyboard) as $callback) {
        if (str_starts_with($callback, 'm:rolepage:')) {
            $prevSeen[] = explode(':', $callback)[2];
        }
    }
    expect($prevSeen)->toContain($last);

    foreach ($ids as $i => $roleId) {
        $callbacks = array_values(array_filter(
            encyclopediaCallbacks($book->page($roleId)->keyboard),
            fn (string $c): bool => str_starts_with($c, 'm:rolepage:')
        ));
        $targets = array_map(fn (string $c): string => explode(':', $c)[2], $callbacks);
        expect($targets)->toContain($ids[($i + 1) % count($ids)])
            ->and($targets)->toContain($ids[($i - 1 + count($ids)) % count($ids)]);
    }
});

it('lists buttons for all roles in the index', function () {
    foreach (encyclopediaLocales() as $locale) {
        $book = new RoleEncyclopedia(new LangPack($locale, LANG_DIR));
        $callbacks = encyclopediaCallbacks($book->index()->keyboard);
        foreach (encyclopediaRoles() as $roleId) {
            expect($callbacks)->toContain('m:rolepage:'.$roleId);
        }
    }
});

it('only produces rolepage or rules callbacks', function () {
    $ids = encyclopediaRoles();
    foreach (encyclopediaLocales() as $locale) {
        $book = new RoleEncyclopedia(new LangPack($locale, LANG_DIR));
        $plans = [$book->index()];
        foreach ($ids as $roleId) {
            $plans[] = $book->page($roleId);
        }
        foreach ($plans as $plan) {
            foreach (encyclopediaCallbacks($plan->keyboard) as $callback) {
                expect($callback)->toMatch('/^m:(rolepage|rules):/');
            }
        }
    }
});
