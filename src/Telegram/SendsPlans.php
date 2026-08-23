<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Telegram;

use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\Enum\ParseModeEnum;
use BAGArt\TelegramBot\TgApi\Types\DTO\InlineKeyboardButtonTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\InlineKeyboardMarkupTypeDTO;
use BAGArt\TelegramBotMafia\GameCoordinator;
use BAGArt\TelegramBotMafia\Presentation\SendPlan;

/**
 * Shared plumbing for module processors: resolve the coordinator, execute a
 * handler, push its SendPlans through TgSenderContract.
 */
trait SendsPlans
{
    protected TgSenderContract $sender;

    /** @param  list<SendPlan>  $plans */
    private function sendPlans(array $plans, $botConfig): void
    {
        foreach ($plans as $plan) {
            $this->sender->send($botConfig, new SendMessageMethodDTO(
                chatId: $plan->chatId,
                text: $plan->text,
                parseMode: ParseModeEnum::HTML,
                replyMarkup: $plan->keyboard !== null
                    ? $this->markup($plan->keyboard)
                    : null,
                disableNotification: $plan->silent ? true : null,
            ));
        }
    }

    /** @param  list<list<array{label: string, callback: string}>>  $rows */
    private function markup(array $rows): InlineKeyboardMarkupTypeDTO
    {
        return new InlineKeyboardMarkupTypeDTO(
            inlineKeyboard: array_map(
                fn (array $row) => array_map(
                    fn (array $button) => new InlineKeyboardButtonTypeDTO(
                        text: $button['label'],
                        callbackData: $button['callback'],
                    ),
                    $row
                ),
                $rows
            )
        );
    }

    private function coordinator(): ?GameCoordinator
    {
        $coordinator = GameCoordinator::instance();
        if ($coordinator !== null) {
            return $coordinator;
        }
        if (function_exists('app')) {
            try {
                $coordinator = app(GameCoordinator::class);
                GameCoordinator::setInstance($coordinator);

                return $coordinator;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
