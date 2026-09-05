<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Web\ChunkAsset;
use BAGArt\TelegramBotMafia\Web\MafiaUi;
use BAGArt\TelegramBotMafia\Web\MafiaUiHandler;
use BAGArt\TelegramBotMenu\Manifest\EffectiveRole;
use BAGArt\TelegramBotMenu\Support\BotRef;
use BAGArt\TelegramBotMenu\Support\ChatRef;
use BAGArt\TelegramBotMenu\Support\ModuleRef;
use BAGArt\TelegramBotMenu\Support\TgUiContext;
use BAGArt\TelegramBotMenu\Support\UserRef;
use BAGArt\TelegramBotMenu\Testing\TgWebUiContractTest;

it('satisfies the TgWebUiContract shape for the mafia module', function () {
    TgWebUiContractTest::assertContractShape(MafiaUi::class, 'mafia');
});

it('declares a chunk entry pointing at the content-hashed bundle (§14.1)', function () {
    $entry = MafiaUi::manifest()->entry;

    expect($entry->type)->toBe('chunk')
        ->and($entry->url)->toBe('/vendor/menu-modules/mafia/'.ChunkAsset::file())
        ->and($entry->url)->toMatch('#^/vendor/menu-modules/mafia/app\.[A-Za-z0-9_-]+\.js$#');
});

it('pairs the session/join route with member role and required chat scope', function () {
    $routes = MafiaUiHandler::routes();

    expect($routes)->toHaveCount(1)
        ->and($routes[0]->method)->toBe('POST')
        ->and($routes[0]->path)->toBe('session/join')
        ->and($routes[0]->minRole)->toBe(EffectiveRole::Member)
        ->and($routes[0]->chatScope)->toBe(\BAGArt\TelegramBotMenu\Manifest\ChatScope::Required);
});

function mafiaContext(?ChatRef $chat): TgUiContext
{
    return new TgUiContext(
        bot: new BotRef('7001', 'stub_bot'),
        chat: $chat,
        module: new ModuleRef('mafia'),
        role: EffectiveRole::Member,
        user: new UserRef(42, 'Stub User', 'en'),
    );
}

it('joins a stub table from the context only — no session or DB identity reads (G9)', function () {
    $handler = new MafiaUiHandler();
    $context = mafiaContext(new ChatRef(-100123, 'Town Square', 'supergroup'));
    $request = new BAGArt\TelegramBotMenu\Support\TgWebRequest(
        botId: '7001',
        tgUserId: 42,
        role: EffectiveRole::Member,
        chatId: -100123,
        locale: 'en',
        payload: [],
        requestId: 'req-1',
        context: $context,
    );

    $response = $handler->handle($request, ['session', 'join']);

    expect($response->status)->toBe(200)
        ->and($response->body['ok'])->toBeTrue()
        ->and($response->body['data']['phase'])->toBe('stub')
        ->and($response->body['data']['chatId'])->toBe(-100123)
        ->and($response->body['data']['userId'])->toBe(42)
        ->and($response->body['data']['locale'])->toBe('en');
});

it('answers 404 for unknown paths and reports a chat-less join honestly', function () {
    $handler = new MafiaUiHandler();
    $context = mafiaContext(null);
    $request = new BAGArt\TelegramBotMenu\Support\TgWebRequest(
        botId: '7001',
        tgUserId: 42,
        role: EffectiveRole::Member,
        chatId: null,
        locale: 'en',
        payload: [],
        requestId: 'req-2',
        context: $context,
    );

    $missing = $handler->handle($request, ['session', 'state']);

    expect($missing->status)->toBe(404)
        ->and($missing->body['error']['code'])->toBe('not_found');
});
