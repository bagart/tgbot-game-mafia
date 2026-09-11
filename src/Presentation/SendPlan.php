<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Presentation;

/**
 * Delivery instruction produced by presenters; executed by the caller through
 * TgSenderContract. Pure data — trivially testable.
 *
 * Edit mode: when $editMessageId is set, use editMessageText instead of
 * sendMessage (live card updates). When null, send a new message.
 *
 * Toast: when $toast is set, also call answerCallbackQuery with the text
 * (or silently if null) — used by CallbackRouterProcessor.
 *
 * @param  list<list<array{label: string, callback: string}>>|null  $keyboard  inline rows
 */
final readonly class SendPlan
{
    public function __construct(
        public string $chatId,
        public string $text,
        public ?array $keyboard = null,
        public bool $silent = false,
        public ?int $editMessageId = null,
        public ?string $toast = null,
        public ?string $callbackQueryId = null,
    ) {
    }
}
