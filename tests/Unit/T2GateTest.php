<?php

declare(strict_types=1);

use BAGArt\TelegramBotAccess\AccessControlContract;
use BAGArt\TelegramBotAccess\AccessDecision;
use BAGArt\TelegramBotAccess\AccessRequest;
use BAGArt\TelegramBotAccess\Grant;
use BAGArt\TelegramBotAccess\GrantScope;
use BAGArt\TelegramBotAccess\InMemoryAccessControl;
use BAGArt\TelegramBotMafia\Auth\T2Gate;
use BAGArt\TelegramBotMafia\Settings\MafiaSettings;

/** Records the decision request and answers a canned result (or throws). */
final class T2GateRecordingAccess implements AccessControlContract
{
    public ?AccessRequest $lastRequest = null;

    public function __construct(
        private readonly bool $allowed = false,
        private readonly bool $throws = false,
    ) {
    }

    public function decide(AccessRequest $request): AccessDecision
    {
        $this->lastRequest = $request;

        if ($this->throws) {
            throw new \RuntimeException('access layer unavailable');
        }

        return $this->allowed ? AccessDecision::allow('test') : AccessDecision::deny('test');
    }
}

it('keeps initiation open for a subject without a platform identity (legacy fallback)', function () {
    $gate = new T2Gate(
        access: new T2GateRecordingAccess(allowed: false),
        platformUserIdOf: fn (int $tgUserId): ?int => null,
        workspaceIdOf: fn (int $chatId): ?int => null,
    );

    expect($gate->canInitiate('bot1', -100500, 777))->toBeTrue();
});

it('keeps initiation open when neither an identity seam nor the identity service is wired', function () {
    $gate = new T2Gate(
        access: new T2GateRecordingAccess(allowed: false),
        workspaceIdOf: fn (int $chatId): ?int => null,
    );

    expect($gate->canInitiate('bot1', -100500, 777))->toBeTrue();
});

it('denies a provisioned subject without a grant and asks with the chat scope', function () {
    $access = new T2GateRecordingAccess(allowed: false);
    $gate = new T2Gate(
        access: $access,
        platformUserIdOf: fn (int $tgUserId): ?int => 42,
        workspaceIdOf: fn (int $chatId): ?int => 7,
    );

    expect($gate->canInitiate('bot1', -100500, 777))->toBeFalse()
        ->and($access->lastRequest?->botId)->toBe('bot1')
        ->and($access->lastRequest?->subjectId)->toBe('42')
        ->and($access->lastRequest?->capability)->toBe(T2Gate::CAPABILITY)
        ->and($access->lastRequest?->chatId)->toBe(-100500)
        ->and($access->lastRequest?->workspaceId)->toBe(7);
});

it('allows a provisioned subject holding the chat-scoped grant', function () {
    $access = (new InMemoryAccessControl())->addGrant(new Grant(
        botId: 'bot1',
        subjectId: '42',
        scope: GrantScope::Chat,
        capability: T2Gate::CAPABILITY,
        chatId: -100500,
    ));
    $gate = new T2Gate(
        access: $access,
        platformUserIdOf: fn (int $tgUserId): ?int => 42,
        workspaceIdOf: fn (int $chatId): ?int => null,
    );

    expect($gate->canInitiate('bot1', -100500, 777))->toBeTrue();
});

it('applies a workspace grant only when the chat links into that workspace', function () {
    $access = (new InMemoryAccessControl())->addGrant(new Grant(
        botId: 'bot1',
        subjectId: '42',
        scope: GrantScope::Workspace,
        capability: T2Gate::CAPABILITY,
        workspaceId: 7,
    ));
    $linked = new T2Gate(
        access: $access,
        platformUserIdOf: fn (int $tgUserId): ?int => 42,
        workspaceIdOf: fn (int $chatId): ?int => 7,
    );
    $otherWorkspace = new T2Gate(
        access: $access,
        platformUserIdOf: fn (int $tgUserId): ?int => 42,
        workspaceIdOf: fn (int $chatId): ?int => 8,
    );

    expect($linked->canInitiate('bot1', -100500, 777))->toBeTrue()
        ->and($otherWorkspace->canInitiate('bot1', -100500, 777))->toBeFalse();
});

it('fails closed when the access layer errors for a provisioned subject', function () {
    $gate = new T2Gate(
        access: new T2GateRecordingAccess(throws: true),
        platformUserIdOf: fn (int $tgUserId): ?int => 42,
        workspaceIdOf: fn (int $chatId): ?int => null,
    );

    expect($gate->canInitiate('bot1', -100500, 777))->toBeFalse();
});

it('ships the t2 gate kill-switch on by default and honors the raw settings key', function () {
    expect((new MafiaSettings())->t2GateEnabled)->toBeTrue()
        ->and(MafiaSettings::fromArray([])->t2GateEnabled)->toBeTrue()
        ->and(MafiaSettings::fromArray(['t2_gate_enabled' => false])->t2GateEnabled)->toBeFalse();
});
