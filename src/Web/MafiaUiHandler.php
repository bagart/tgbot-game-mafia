<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Web;

use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\GameSnapshot;
use BAGArt\TelegramBotMafia\GameCoordinator;
use BAGArt\TelegramBotMafia\Contracts\MafiaStateStoreContract;
use BAGArt\TelegramBotMenu\Contracts\TgWebApiHandlerContract;
use BAGArt\TelegramBotMenu\Manifest\ChatScope;
use BAGArt\TelegramBotMenu\Manifest\EffectiveRole;
use BAGArt\TelegramBotMenu\Support\TgUiContext;
use BAGArt\TelegramBotMenu\Support\TgWebApiRoute;
use BAGArt\TelegramBotMenu\Support\TgWebRequest;
use BAGArt\TelegramBotMenu\Support\TgWebResponse;

/**
 * Mafia Mini App webApi surface. Routes game actions through the chunk
 * protocol (bridge.fetch) to the same GameCoordinator used by Telegram
 * callbacks. Identity comes exclusively from the injected TgUiContext.
 */
final class MafiaUiHandler implements TgWebApiHandlerContract
{
    private const SPECTATOR_DELAY_SECONDS = 30;

    public static function routes(): array
    {
        return [
            new TgWebApiRoute('POST', 'session/join', EffectiveRole::Member, chatScope: ChatScope::Required),
            new TgWebApiRoute('GET', 'game/snapshot', EffectiveRole::Member, chatScope: ChatScope::Required),
            new TgWebApiRoute('GET', 'game/lobby', EffectiveRole::Member, chatScope: ChatScope::Required),
            new TgWebApiRoute('POST', 'game/night-action', EffectiveRole::Member, chatScope: ChatScope::Required),
            new TgWebApiRoute('POST', 'game/vote', EffectiveRole::Member, chatScope: ChatScope::Required),
            new TgWebApiRoute('POST', 'game/skip-night', EffectiveRole::Member, chatScope: ChatScope::Required),
        ];
    }

    public function handle(TgWebRequest $request, array $path): TgWebResponse
    {
        return match ($path) {
            ['session', 'join'] => $this->join($request->context),
            ['game', 'snapshot'] => $this->snapshot($request->context),
            ['game', 'lobby'] => $this->lobby($request->context),
            ['game', 'night-action'] => $this->nightAction($request, $request->context),
            ['game', 'vote'] => $this->vote($request, $request->context),
            ['game', 'skip-night'] => $this->skipNight($request->context),
            default => TgWebResponse::error('not_found', 'Unknown mafia route', 404, $request->requestId),
        };
    }

    private function join(TgUiContext $context): TgWebResponse
    {
        $chat = $context->chat;
        if ($chat === null) {
            return TgWebResponse::error('bad_request', 'Chat scope required.', 400);
        }

        return TgWebResponse::ok([
            'phase' => 'lobby',
            'chatId' => $chat->id,
            'userId' => $context->user->id,
            'locale' => $context->user->locale,
            'message' => 'Joined lobby',
        ]);
    }

    private function snapshot(TgUiContext $context): TgWebResponse
    {
        $chat = $context->chat;
        if ($chat === null) {
            return TgWebResponse::error('bad_request', 'Chat scope required.', 400);
        }

        $store = app(MafiaStateStoreContract::class);
        $snapshot = $store->gameByChat((string) $chat->id);

        if ($snapshot === null) {
            return TgWebResponse::ok(['active' => false]);
        }

        $userId = (string) $context->user->id;
        $viewerSeat = $snapshot->seatByUser($userId);
        $isSpectator = $viewerSeat === null;
        $isDead = $viewerSeat !== null && ! $viewerSeat->alive;

        if ($isSpectator || $isDead) {
            $snapshot = $this->applySpectatorDelay($snapshot);
        }

        return TgWebResponse::ok([
            'active' => true,
            'snapshot' => json_decode($snapshot->toJson(), true, 512, JSON_THROW_ON_ERROR),
            'viewerSeat' => $viewerSeat?->seat,
            'isSpectator' => $isSpectator || $isDead,
        ]);
    }

    private function lobby(TgUiContext $context): TgWebResponse
    {
        $chat = $context->chat;
        if ($chat === null) {
            return TgWebResponse::error('bad_request', 'Chat scope required.', 400);
        }

        $store = app(MafiaStateStoreContract::class);
        $snapshot = $store->gameByChat((string) $chat->id);

        if ($snapshot === null) {
            return TgWebResponse::ok(['active' => false, 'seats' => [], 'phase' => null]);
        }

        return TgWebResponse::ok([
            'active' => true,
            'phase' => $snapshot->phase->value,
            'dayNumber' => $snapshot->dayNumber,
            'seats' => array_map(fn ($s) => [
                'seat' => $s->seat,
                'name' => $s->name,
                'isBot' => $s->isBot,
                'alive' => $s->alive,
            ], $snapshot->seats),
        ]);
    }

    private function nightAction(TgWebRequest $request, TgUiContext $context): TgWebResponse
    {
        $chat = $context->chat;
        if ($chat === null) {
            return TgWebResponse::error('bad_request', 'Chat scope required.', 400);
        }

        $store = app(MafiaStateStoreContract::class);
        $snapshot = $store->gameByChat((string) $chat->id);

        if ($snapshot === null || $snapshot->phase !== PhaseEnum::Night) {
            return TgWebResponse::error('bad_request', 'Not in night phase.', 400);
        }

        $userId = (string) $context->user->id;
        $seat = $snapshot->seatByUser($userId);
        if ($seat === null || ! $seat->alive) {
            return TgWebResponse::error('forbidden', 'You are not an active player.', 403);
        }

        $data = $request->payload ?? [];
        $targetSeat = $data['targetSeat'] ?? null;
        $actionType = $data['actionType'] ?? null;

        if (! is_string($actionType)) {
            return TgWebResponse::error('bad_request', 'actionType is required.', 400);
        }

        $coordinator = GameCoordinator::instance();
        if ($coordinator === null) {
            return TgWebResponse::error('unavailable', 'Game coordinator not available.', 503);
        }

        $coordinator->castNight(
            gameId: $snapshot->gameId,
            userId: $userId,
            targetSeat: is_int($targetSeat) ? $targetSeat : null,
        );

        return TgWebResponse::ok(['ok' => true]);
    }

    private function vote(TgWebRequest $request, TgUiContext $context): TgWebResponse
    {
        $chat = $context->chat;
        if ($chat === null) {
            return TgWebResponse::error('bad_request', 'Chat scope required.', 400);
        }

        $store = app(MafiaStateStoreContract::class);
        $snapshot = $store->gameByChat((string) $chat->id);

        if ($snapshot === null || $snapshot->phase !== PhaseEnum::DayVoting) {
            return TgWebResponse::error('bad_request', 'Not in voting phase.', 400);
        }

        $userId = (string) $context->user->id;
        $seat = $snapshot->seatByUser($userId);
        if ($seat === null || ! $seat->alive) {
            return TgWebResponse::error('forbidden', 'You are not an active player.', 403);
        }

        $data = $request->payload ?? [];
        $targetSeat = $data['targetSeat'] ?? null;

        if (! is_int($targetSeat)) {
            return TgWebResponse::error('bad_request', 'targetSeat is required.', 400);
        }

        $coordinator = GameCoordinator::instance();
        if ($coordinator === null) {
            return TgWebResponse::error('unavailable', 'Game coordinator not available.', 503);
        }

        $coordinator->castVote(
            gameId: $snapshot->gameId,
            userId: $userId,
            targetSeat: $targetSeat,
        );

        return TgWebResponse::ok(['ok' => true]);
    }

    private function skipNight(TgUiContext $context): TgWebResponse
    {
        $chat = $context->chat;
        if ($chat === null) {
            return TgWebResponse::error('bad_request', 'Chat scope required.', 400);
        }

        $store = app(MafiaStateStoreContract::class);
        $snapshot = $store->gameByChat((string) $chat->id);

        if ($snapshot === null) {
            return TgWebResponse::error('bad_request', 'No active game.', 400);
        }

        $coordinator = GameCoordinator::instance();
        if ($coordinator === null) {
            return TgWebResponse::error('unavailable', 'Game coordinator not available.', 503);
        }

        $coordinator->skipNight(
            gameId: $snapshot->gameId,
            userId: (string) $context->user->id,
        );

        return TgWebResponse::ok(['ok' => true]);
    }

    private function applySpectatorDelay(GameSnapshot $snapshot): GameSnapshot
    {
        $delay = time() - self::SPECTATOR_DELAY_SECONDS;

        return $snapshot->with(deadlineAt: max($snapshot->deadlineAt, $delay));
    }
}
