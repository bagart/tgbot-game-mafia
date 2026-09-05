<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Console;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\InlineKeyboardButtonTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\StyleEnum;
use BAGArt\TelegramBot\TgApi\Types\DTO\InlineKeyboardMarkupTypeDTO;
use BAGArt\TelegramBotMafia\GameCoordinator;
use BAGArt\TelegramBotMafia\Presentation\SendPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deadline enforcement fallback (RUN-3): advances every overdue active game
 * and delivers the resulting plans. Scheduled by the host routes/console.php
 * (same pattern as summarizer:digests / tts:prune). Lazy advance on any
 * interaction stays the primary path; this only catches idle chats.
 */
class MafiaSweepCommand extends Command
{
    protected $signature = 'mafia:sweep {--dry : report overdue games without advancing}';

    protected $description = 'Advance overdue mafia game phases and deliver their announcements';

    public function handle(): int
    {
        $coordinator = GameCoordinator::instance() ?? app(GameCoordinator::class);
        $dry = (bool) $this->option('dry');
        $now = time();
        $advanced = 0;
        $overdue = 0;

        foreach ($coordinator->store()->activeGames() as $snapshot) {
            if ($snapshot->pausedAt !== null) {
                continue;
            }
            if ($snapshot->deadlineAt > $now) {
                continue;
            }
            $overdue++;
            if ($dry) {
                $this->line("overdue: {$snapshot->gameId} phase={$snapshot->phase->value}");

                continue;
            }

            $plans = $coordinator->advanceIfOverdue($snapshot->gameId);
            if ($plans === []) {
                continue;
            }
            $advanced++;
            $this->sendPlans($coordinator, $snapshot->botId, $plans);
        }

        $this->info("mafia:sweep done: {$overdue} overdue, ".($dry ? 'dry-run' : "{$advanced} advanced"));

        return self::SUCCESS;
    }

    /** @param  list<SendPlan>  $plans */
    private function sendPlans(GameCoordinator $coordinator, ?string $botId, array $plans): void
    {
        $config = $this->botConfig($botId);
        if ($config === null) {
            $this->warn('no sender for bot "'.($botId ?? 'null').'" — plans dropped');

            return;
        }
        $sender = app(TgSenderContract::class);
        foreach ($plans as $plan) {
            $sender->send($config, new SendMessageMethodDTO(
                chatId: $plan->chatId,
                text: $plan->text,
                parseMode: \BAGArt\TelegramBot\TgApi\Methods\Enum\ParseModeEnum::HTML,
                replyMarkup: $plan->keyboard !== null ? $this->markup($plan->keyboard) : null,
                disableNotification: $plan->silent ? true : null,
            ));
        }
    }

    private function botConfig(?string $botId): ?TgBotConfig
    {
        if ($botId === null) {
            return null;
        }
        $token = DB::table('tg_bots')->where('bot_id', $botId)->value('token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            return new TgBotConfig(token: $token, botId: $botId);
        } catch (\Throwable) {
            return null;
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
                        // GRP-12: confirm=success, kick/end=danger
                        style: isset($button['style']) ? StyleEnum::from($button['style']) : null,
                    ),
                    $row
                ),
                $rows
            )
        );
    }
}
