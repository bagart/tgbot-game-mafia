<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Web;

use BAGArt\TelegramBotMenu\Contracts\TgWebApiHandlerContract;
use BAGArt\TelegramBotMenu\Manifest\ChatScope;
use BAGArt\TelegramBotMenu\Manifest\EffectiveRole;
use BAGArt\TelegramBotMenu\Support\TgUiContext;
use BAGArt\TelegramBotMenu\Support\TgWebApiRoute;
use BAGArt\TelegramBotMenu\Support\TgWebRequest;
use BAGArt\TelegramBotMenu\Support\TgWebResponse;

/**
 * Stub webApi surface for the mafia chunk (task 18). G9 exemplar: the
 * handler reads identity EXCLUSIVELY from the injected TgUiContext — no
 * session reads, no DB identity lookups, no repositories. Transport proof
 * only; real game endpoints belong to the mafia module's own plan.
 */
final class MafiaUiHandler implements TgWebApiHandlerContract
{
    public static function routes(): array
    {
        return [
            new TgWebApiRoute('POST', 'session/join', EffectiveRole::Member, chatScope: ChatScope::Required),
        ];
    }

    public function handle(TgWebRequest $request, array $path): TgWebResponse
    {
        if ($path === ['session', 'join']) {
            return $this->join($request->context);
        }

        return TgWebResponse::error('not_found', 'Unknown mafia route', 404, $request->requestId);
    }

    private function join(TgUiContext $context): TgWebResponse
    {
        $chat = $context->chat;

        return TgWebResponse::ok([
            'phase' => 'stub',
            'chatId' => $chat?->id,
            'userId' => $context->user->id,
            'locale' => $context->user->locale,
            'message' => "Stub table joined in chat {$chat?->id} as user {$context->user->id}",
        ]);
    }
}
