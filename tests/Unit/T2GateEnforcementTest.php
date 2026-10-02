<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiMethodDTOContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\AnswerCallbackQueryMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\CallbackQueryTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;
use BAGArt\TelegramBotAccess\Grant;
use BAGArt\TelegramBotAccess\GrantScope;
use BAGArt\TelegramBotAccess\InMemoryAccessControl;
use BAGArt\TelegramBotMafia\Auth\T2Gate;
use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\GameSnapshot;
use BAGArt\TelegramBotMafia\Core\SeatState;
use BAGArt\TelegramBotMafia\GameCoordinator;
use BAGArt\TelegramBotMafia\Support\CallbackData;
use BAGArt\TelegramBotMafia\Telegram\CallbackRouterProcessor;
use BAGArt\TelegramBotMafia\Telegram\PlayCommandProcessor;
use BAGArt\TelegramBotMafia\Tests\Support\CoordinatorFactory;

// Package-local vendor ships no telegram-bot-lib (host-provided); fall back
// to the platform autoloader so processor-level tests can run in dev checkouts.
if (! interface_exists(TgSenderContract::class)) {
    $hostAutoload = dirname(__DIR__, 5).'/vendor/autoload.php';
    if (is_file($hostAutoload)) {
        require_once $hostAutoload;
    }
}

/** Recording no-op sender standing in for the outbound queue. */
final class T2GateSenderSpy implements TgSenderContract
{
    /** @var list<TgApiMethodDTOContract> */
    public array $sent = [];

    public function send(TgBotConfig $botConfig, TgApiMethodDTOContract $dto): void
    {
        $this->sent[] = $dto;
    }
}

/** /play processor with the container-resolved gate swapped for a test seam. */
final class T2GatePlayProbe extends PlayCommandProcessor
{
    public function __construct(
        private readonly TgSenderContract $probeSender,
        public ?T2Gate $probeGate = null,
    ) {
        $this->sender = $probeSender;
    }

    protected function gate(): ?T2Gate
    {
        return $this->probeGate;
    }
}

/** Callback router with the container-resolved gate swapped for a test seam. */
final class T2GateRouterProbe extends CallbackRouterProcessor
{
    public function __construct(
        private readonly TgSenderContract $probeSender,
        public ?T2Gate $probeGate = null,
    ) {
        $this->sender = $probeSender;
    }

    protected function gate(): ?T2Gate
    {
        return $this->probeGate;
    }
}

function t2DenyingGate(): T2Gate
{
    return new T2Gate(
        access: new InMemoryAccessControl(),
        platformUserIdOf: fn (int $tgUserId): ?int => 42,
        workspaceIdOf: fn (int $chatId): ?int => null,
    );
}

function t2GrantingGate(): T2Gate
{
    return new T2Gate(
        access: (new InMemoryAccessControl())->addGrant(new Grant(
            botId: 'bot1',
            subjectId: '42',
            scope: GrantScope::Chat,
            capability: T2Gate::CAPABILITY,
            chatId: -100500,
        )),
        platformUserIdOf: fn (int $tgUserId): ?int => 42,
        workspaceIdOf: fn (int $chatId): ?int => null,
    );
}

function t2PlayMessage(): MessageTypeDTO
{
    return new MessageTypeDTO(
        messageId: 1,
        date: 1_000,
        chat: new ChatTypeDTO(id: '-100500', type: ChatPropTypeEnum::SUPERGROUP, title: 'Lobby'),
        from: new UserTypeDTO(id: '777', isBot: false, firstName: 'Ann'),
        text: '/play',
    );
}

/** Finished group game (chat -100500) so the m:again rematch path is reachable. */
function t2FinishedGroupGame(): GameCoordinator
{
    $coordinator = CoordinatorFactory::make();
    GameCoordinator::setInstance($coordinator);

    $room = $coordinator->createRoom(
        kind: 'group',
        chatId: '-100500',
        title: 'Lobby',
        hostId: 'host1',
        hostName: 'Host',
        min: 2,
        max: 6,
        checkedRoles: [],
        locale: 'en',
    );
    $coordinator->store()->saveSnapshot(new GameSnapshot(
        gameId: 'g1',
        roomId: $room->id,
        chatId: '-100500',
        locale: 'en',
        phase: PhaseEnum::Ended,
        phaseNumber: 1,
        dayNumber: 1,
        deadlineAt: 1_000,
        mirrorOn: false,
        seats: [new SeatState(seat: 1, userId: 'p1', name: 'Player', isBot: false, role: null)],
    ));

    return $coordinator;
}

afterEach(function () {
    GameCoordinator::setInstance(null);
});

it('creates the group lobby when no gate is wired (legacy fallback)', function () {
    $coordinator = CoordinatorFactory::make();
    GameCoordinator::setInstance($coordinator);

    $spy = new T2GateSenderSpy();
    (new T2GatePlayProbe($spy))->process(t2PlayMessage(), new TgBotConfig('token', 'bot1'));

    expect($coordinator->rooms()->findByChat('-100500', 'lobby'))->not->toBeNull()
        ->and($spy->sent)->toHaveCount(1)
        ->and($spy->sent[0])->toBeInstanceOf(SendMessageMethodDTO::class);
});

it('blocks group room creation when the T2 gate denies game.initiate', function () {
    $coordinator = CoordinatorFactory::make();
    GameCoordinator::setInstance($coordinator);

    $spy = new T2GateSenderSpy();
    (new T2GatePlayProbe($spy, t2DenyingGate()))->process(t2PlayMessage(), new TgBotConfig('token', 'bot1'));

    expect($coordinator->rooms()->findByChat('-100500', 'lobby'))->toBeNull()
        ->and($spy->sent)->toHaveCount(1)
        ->and($spy->sent[0])->toBeInstanceOf(SendMessageMethodDTO::class);
});

it('creates the group room when the T2 gate grants game.initiate', function () {
    $coordinator = CoordinatorFactory::make();
    GameCoordinator::setInstance($coordinator);

    $spy = new T2GateSenderSpy();
    (new T2GatePlayProbe($spy, t2GrantingGate()))->process(t2PlayMessage(), new TgBotConfig('token', 'bot1'));

    expect($coordinator->rooms()->findByChat('-100500', 'lobby'))->not->toBeNull();
});

it('answers a denied m:again rematch with a toast and creates no new plans', function () {
    $coordinator = t2FinishedGroupGame();

    $spy = new T2GateSenderSpy();
    (new T2GateRouterProbe($spy, t2DenyingGate()))->process(
        new CallbackQueryTypeDTO(
            id: 'q1',
            from: new UserTypeDTO(id: '777', isBot: false, firstName: 'Ann'),
            chatInstance: 'ci1',
            data: CallbackData::encode('again', 'g1'),
        ),
        new TgBotConfig('token', 'bot1'),
    );

    $answers = array_filter($spy->sent, fn (TgApiMethodDTOContract $d): bool => $d instanceof AnswerCallbackQueryMethodDTO);
    $messages = array_filter($spy->sent, fn (TgApiMethodDTOContract $d): bool => $d instanceof SendMessageMethodDTO);

    expect($answers)->toHaveCount(1)
        ->and($messages)->toBe([]);
});

it('runs the m:again rematch when the T2 gate grants game.initiate', function () {
    $coordinator = t2FinishedGroupGame();

    $spy = new T2GateSenderSpy();
    (new T2GateRouterProbe($spy, t2GrantingGate()))->process(
        new CallbackQueryTypeDTO(
            id: 'q2',
            from: new UserTypeDTO(id: '777', isBot: false, firstName: 'Ann'),
            chatInstance: 'ci1',
            data: CallbackData::encode('again', 'g1'),
        ),
        new TgBotConfig('token', 'bot1'),
    );

    $messages = array_filter($spy->sent, fn (TgApiMethodDTOContract $d): bool => $d instanceof SendMessageMethodDTO);

    expect($messages)->not->toBeEmpty()
        ->and($coordinator->store()->loadSnapshot('g1')?->roomId)->not->toBeNull();
});
