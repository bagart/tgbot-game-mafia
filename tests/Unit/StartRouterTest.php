<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiMethodDTOContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\CallbackQueryTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\GameSnapshot;
use BAGArt\TelegramBotMafia\Core\SeatState;
use BAGArt\TelegramBotMafia\GameCoordinator;
use BAGArt\TelegramBotMafia\I18n\LangPack;
use BAGArt\TelegramBotMafia\Onboarding\WelcomeCard;
use BAGArt\TelegramBotMafia\State\InMemoryProfileStore;
use BAGArt\TelegramBotMafia\Telegram\CallbackRouterProcessor;
use BAGArt\TelegramBotMafia\Tests\Support\CoordinatorFactory;

// Package-local vendor ships no telegram-bot-lib (host-provided); fall back
// to the platform autoloader so processor-level tests can run in dev checkouts.
if (! interface_exists(TgSenderContract::class)) {
    $onbHostAutoload = dirname(__DIR__, 5).'/vendor/autoload.php';
    if (is_file($onbHostAutoload)) {
        require_once $onbHostAutoload;
    }
}

const ONB_LANG_DIR = __DIR__.'/../../resources/lang';
const ONB_LOCALES = ['en', 'ru', 'es', 'zh'];

function welcomeCallbacks(?array $keyboard): array
{
    $out = [];
    foreach ((array) $keyboard as $row) {
        foreach ($row as $button) {
            // plan keyboards carry plain arrays; sent DTOs carry button objects
            $out[] = is_array($button) ? (string) $button['callback'] : (string) $button->callbackData;
        }
    }

    return $out;
}

function welcomeLangTarget(string $locale): string
{
    foreach (welcomeCallbacks((new WelcomeCard(new LangPack($locale, ONB_LANG_DIR), '42', $locale))->card()->keyboard) as $cb) {
        if (str_starts_with($cb, 'm:lang:')) {
            return substr($cb, strlen('m:lang:'));
        }
    }

    return '';
}

/** Processor with the trait's protected sender swapped for the given fake. */
function onbRouter(object $sender): CallbackRouterProcessor
{
    $router = new CallbackRouterProcessor();
    Closure::bind(function () use ($sender): void {
        $this->sender = $sender;
    }, $router, $router)();

    return $router;
}

/** Recording no-op sender standing in for the outbound queue. */
final class OnbFakeSender implements TgSenderContract
{
    /** @var list<SendMessageMethodDTO> */
    public array $sent = [];

    public function send(TgBotConfig $botConfig, TgApiMethodDTOContract $dto): void
    {
        $this->sent[] = $dto;
    }
}

it('offers rules and a language callback on the welcome card in every locale', function () {
    foreach (ONB_LOCALES as $locale) {
        $card = new WelcomeCard(new LangPack($locale, ONB_LANG_DIR), '42', $locale);
        $callbacks = welcomeCallbacks($card->card()->keyboard);

        expect($callbacks)->toContain('m:rules:0')
            ->and(welcomeLangTarget($locale))->toBeIn(ONB_LOCALES);
    }
});

it('cycles the language button en→ru→es→zh and persists the pick', function () {
    $cycle = ['en' => 'ru', 'ru' => 'es', 'es' => 'zh', 'zh' => 'en'];
    foreach ($cycle as $from => $to) {
        expect(welcomeLangTarget($from))->toBe($to);
    }

    $store = new InMemoryProfileStore();
    expect($store->preferredLocale('u1'))->toBeNull();
    foreach ($cycle as $from => $to) {
        $store->setPreferredLocale('u1', welcomeLangTarget($from));
        expect($store->preferredLocale('u1'))->toBe($to);
    }
});

it('lets the profile preference win over the room locale in localeFor()', function () {
    $c = CoordinatorFactory::make();
    $room = $c->createRoom(
        kind: 'group',
        chatId: null,
        title: 'Room',
        hostId: 'u7',
        hostName: 'Host',
        min: 5,
        max: 10,
        checkedRoles: [],
        locale: 'ru',
    );
    $c->store()->saveSnapshot(new GameSnapshot(
        gameId: 'g1',
        roomId: $room->id,
        chatId: null,
        locale: 'ru',
        phase: PhaseEnum::Night,
        phaseNumber: 1,
        dayNumber: 1,
        deadlineAt: 1000,
        mirrorOn: false,
        seats: [new SeatState(seat: 1, userId: 'u7', name: 'Host', isBot: false, role: null)],
    ));

    // no preference yet → room locale
    expect($c->localeFor('u7'))->toBe('ru');

    // explicit profile choice overrides the room
    $c->profiles()->setPreferredLocale('u7', 'zh');
    expect($c->localeFor('u7'))->toBe('zh');

    // invalid stored values fall back to the room locale
    $c->profiles()->setPreferredLocale('u7', 'fr');
    expect($c->localeFor('u7'))->toBe('ru');
});

it('answers m:lang callbacks with a re-rendered card and no other plans', function () {
    $coordinator = CoordinatorFactory::make();
    GameCoordinator::setInstance($coordinator);

    $sender = new OnbFakeSender();
    $router = onbRouter($sender);
    $query = new CallbackQueryTypeDTO(
        id: 'q1',
        from: new UserTypeDTO(id: '777', isBot: false, firstName: 'Ann'),
        chatInstance: 'ci1',
        data: 'm:lang:ru',
    );

    $router->process($query, new TgBotConfig('token', 'bot1'));

    expect($sender->sent)->toHaveCount(1)
        ->and($sender->sent[0]->text)->toContain('<b>')
        ->and(welcomeCallbacks($sender->sent[0]->replyMarkup?->inlineKeyboard ?? []))->toContain('m:lang:es');
    expect($coordinator->profiles()->preferredLocale('777'))->toBe('ru');

    GameCoordinator::setInstance(null);
});

it('yields no plans for m:onbsoon stub buttons', function () {
    $coordinator = CoordinatorFactory::make();
    GameCoordinator::setInstance($coordinator);

    $sender = new OnbFakeSender();
    $router = onbRouter($sender);
    $query = new CallbackQueryTypeDTO(
        id: 'q2',
        from: new UserTypeDTO(id: '777', isBot: false, firstName: 'Ann'),
        chatInstance: 'ci1',
        data: 'm:onbsoon:quickplay',
    );

    $router->process($query, new TgBotConfig('token', 'bot1'));

    expect($sender->sent)->toBe([])
        ->and($coordinator->profiles()->preferredLocale('777'))->toBeNull();

    GameCoordinator::setInstance(null);
});
