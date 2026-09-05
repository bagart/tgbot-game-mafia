<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Presentation;

/**
 * Delivery instruction produced by presenters; executed by the caller through
 * TgSenderContract. Pure data — trivially testable.
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
    ) {
    }
}
