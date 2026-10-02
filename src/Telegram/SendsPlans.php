<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Telegram;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\AnswerCallbackQueryMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\DTO\EditMessageTextMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\Enum\ParseModeEnum;
use BAGArt\TelegramBot\TgApi\Types\DTO\InlineKeyboardButtonTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\StyleEnum;
use BAGArt\TelegramBot\TgApi\Types\DTO\InlineKeyboardMarkupTypeDTO;
use BAGArt\TelegramBotMafia\Auth\T2Gate;
use BAGArt\TelegramBotMafia\GameCoordinator;
use BAGArt\TelegramBotMafia\Presentation\SendPlan;
use BAGArt\TelegramBotMafia\Settings\MafiaSettings;
use BAGArt\TelegramBotMafia\Settings\MafiaSettingsService;

/**
 * Shared plumbing for module processors: resolve the coordinator, execute a
 * handler, push its SendPlans through TgSenderContract.
 *
 * Supports three delivery modes:
 * - sendMessage: new message (default)
 * - editMessageText: in-place update when plan->editMessageId is set
 * - answerCallbackQuery: toast notification when plan->callbackQueryId is set
 */
trait SendsPlans
{
    protected TgSenderContract $sender;

    /**
     * Send plans and return chat_id => messageId pairs for new messages.
     *
     * @param  list<SendPlan>  $plans
     * @return array<string, int>  chatId => last sent messageId
     */
    private function sendPlans(array $plans, $botConfig): array
    {
        $sent = [];
        foreach ($plans as $plan) {
            // Toast via answerCallbackQuery (always first, even on edit)
            if ($plan->callbackQueryId !== null) {
                $this->sender->send($botConfig, new AnswerCallbackQueryMethodDTO(
                    callbackQueryId: $plan->callbackQueryId,
                    text: $plan->toast,
                ));
            }

            if ($plan->editMessageId !== null) {
                // Edit existing message in-place (live card update)
                $this->sender->send($botConfig, new EditMessageTextMethodDTO(
                    text: $plan->text,
                    chatId: $plan->chatId,
                    messageId: $plan->editMessageId,
                    parseMode: ParseModeEnum::HTML,
                    replyMarkup: $plan->keyboard !== null
                        ? $this->markup($plan->keyboard)
                        : null,
                ));
            } else {
                // Send new message
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

        return $sent;
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
                        // GRP-12: confirm=success, kick/end=danger
                        style: isset($button['style']) ? StyleEnum::from($button['style']) : null,
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

    /**
     * PLAT-D14: resolved T2 gate, or null when the access-control wiring is
     * absent (standalone package runs, unit tests) — callers then keep the
     * legacy open behavior.
     */
    protected function gate(): ?T2Gate
    {
        try {
            if (! function_exists('app')) {
                return null;
            }
            $container = app();
            if (! $container->bound(T2Gate::class)) {
                return null;
            }

            return $container->make(T2Gate::class);
        } catch (\Throwable) {
            return null;
        }
    }

    /** PLAT-5: effective chat settings; package defaults when unbound. */
    protected function chatSettings(TgBotConfig $botConfig, int|string $chatId): MafiaSettings
    {
        if (! function_exists('app') || ! app()->bound(MafiaSettingsService::class) || ! is_numeric($chatId)) {
            return new MafiaSettings();
        }

        try {
            return app(MafiaSettingsService::class)->get($botConfig->botId, (int) $chatId);
        } catch (\Throwable) {
            return new MafiaSettings();
        }
    }

    /**
     * PLAT-D14: may this member start a game (T2 game.initiate)? The
     * per-chat kill-switch, a missing gate or an unprovisioned subject keep
     * the legacy open behavior; only a decision from the bound gate denies.
     */
    protected function gameInitiateAllowed(
        MafiaSettings $settings,
        TgBotConfig $botConfig,
        int|string $chatId,
        int $tgUserId,
    ): bool {
        if (! $settings->t2GateEnabled) {
            return true;
        }
        $gate = $this->gate();
        if ($gate === null) {
            return true;
        }

        return $gate->canInitiate($botConfig->botId, (int) $chatId, $tgUserId);
    }
}
