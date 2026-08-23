<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Bots\NicknameFactory;
use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\PublicStateView;
use BAGArt\TelegramBotMafia\Core\SeatState;
use BAGArt\TelegramBotMafia\I18n\LangPack;
use BAGArt\TelegramBotMafia\Support\CallbackData;

const LANG_DIR_2 = __DIR__.'/../../resources/lang';

it('generates unique filler nicknames beyond the pool size', function () {
    $factory = new NicknameFactory(new LangPack('en', LANG_DIR_2), fn (int $max): int => 0);

    $names = [];
    for ($i = 0; $i < 30; $i++) {
        $names[] = $factory->next();
    }

    expect(count(array_unique($names)))->toBe(30)
        ->and($names[0])->not->toBe($names[1]); // first collision got a suffix
});

it('keeps the fairness firewall: brains see only filtered views', function () {
    $view = PublicStateView::fromSeats(
        [
            new SeatState(1, 'a', 'A', false, 'mafia'),
            new SeatState(2, 'b', 'B', false, 'mafia'),
            new SeatState(3, 'c', 'C', false, 'civilian'),
        ],
        PhaseEnum::Night,
        1,
        teammateSeats: [1, 2],
    );

    expect($view->teammateSeats)->toBe([1, 2])
        // views carry no roles at all — brains cannot know who is who
        ->and(json_encode($view))->not->toContain('mafia"');
});

it('roundtrips callback payloads within telegram byte limits', function () {
    $data = CallbackData::encode('v', 'abc123', '7');

    expect(strlen($data))->toBeLessThanOrEqual(64)
        ->and(CallbackData::decode($data))->toBe([
            'action' => 'v', 'gameId' => 'abc123', 'payload' => '7',
        ]);

    expect(CallbackData::decode('other:format'))->toBeNull();
    expect(fn () => CallbackData::encode('x', str_repeat('a', 70)))
        ->toThrow(InvalidArgumentException::class);
});
