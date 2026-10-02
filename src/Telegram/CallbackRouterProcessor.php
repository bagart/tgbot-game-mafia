<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Telegram;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Processing\Processors\TgModuleProcessorContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiTypeDTOContract;
use BAGArt\TelegramBot\Processing\BotProcessorContext;
use BAGArt\TelegramBot\Processing\ErrorHandling\ProcessorErrorContext;
use BAGArt\TelegramBot\TgApi\Methods\DTO\AnswerCallbackQueryMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\CallbackQueryTypeDTO;
use BAGArt\TelegramBotMafia\Contracts\MafiaDlqContract;
use BAGArt\TelegramBotMafia\GameCoordinator;
use BAGArt\TelegramBotMafia\I18n\LangPack;
use BAGArt\TelegramBotMafia\I18n\LocaleResolver;
use BAGArt\TelegramBotMafia\Presentation\SendPlan;
use BAGArt\TelegramBotMafia\Onboarding\RulesWiki;
use BAGArt\TelegramBotMafia\Onboarding\WelcomeCard;
use BAGArt\TelegramBotMafia\Presentation\RoleEncyclopedia;
use BAGArt\TelegramBotMafia\Support\CallbackData;
use Illuminate\Support\Facades\Log;

/**
 * Routes every "m:*" inline callback: lobby actions, night menus, votes.
 * Game context rides in callback data (see Support\CallbackData).
 */
class CallbackRouterProcessor implements TgModuleProcessorContract
{
    use SendsPlans;

    public static function moduleId(): string
    {
        return 'mafia';
    }

    public static function build(BotProcessorContext $context): self
    {
        $self = new self();
        $self->sender = $context->tgSender;

        return $self;
    }

    public function support(TgApiTypeDTOContract $dto, TgBotConfig $botConfig, ?string $action = null): bool
    {
        return $dto instanceof CallbackQueryTypeDTO
            && str_starts_with((string) ($dto->data ?? ''), 'm:');
    }

    public function isStrictOrdered(TgApiTypeDTOContract $dto, TgBotConfig $botConfig, ?string $action = null): bool
    {
        return false;
    }

    public function process(TgApiTypeDTOContract $dto, TgBotConfig $botConfig, ?string $action = null): void
    {
        if (! $dto instanceof CallbackQueryTypeDTO) {
            return;
        }
        $parsed = CallbackData::decode($dto->data ?? null);
        $coordinator = $this->coordinator();
        if ($parsed === null || $coordinator === null || $dto->from === null) {
            return;
        }
        $userId = (string) $dto->from->id;
        $name = trim(($dto->from->first_name ?? '').' '.($dto->from->last_name ?? ''));
        if ($name === '') {
            $name = $userId;
        }

        try {
            $this->processCallback($parsed, $dto, $coordinator, $userId, $name, $botConfig);
        } catch (\Throwable $e) {
            Log::error('mafia.callback.failed', [
                'gameId' => $parsed['gameId'],
                'action' => $parsed['action'],
                'userId' => $userId,
                'error' => $e->getMessage(),
            ]);

            $this->pushToDlq($botConfig, $dto->data ?? '', $userId, $e);

            $this->sender->send($botConfig, new AnswerCallbackQueryMethodDTO(
                callbackQueryId: $dto->id,
                text: 'Error processing action. It will be retried.',
            ));
        }
    }

    /**
     * @param  array{action: string, gameId: string, payload: string|null}  $parsed
     */
    private function processCallback(
        array $parsed,
        CallbackQueryTypeDTO $dto,
        GameCoordinator $coordinator,
        string $userId,
        string $name,
        TgBotConfig $botConfig,
    ): void {
        ['action' => $act, 'gameId' => $id, 'payload' => $payload] = $parsed;

        // Track the callback's message ID for edit-in-place during phase transitions
        if ($dto->message?->chat !== null && $dto->message?->message_id !== null) {
            $chatId = (string) $dto->message->chat->id;
            $coordinator->trackGroupMessage($id, 'callback', $chatId, (int) $dto->message->message_id);
        }

        switch ($act) {
            case 'join':
                $result = $coordinator->join($id, $userId, $name);
                break;

            case 'leave':
                $result = $coordinator->leave($id, $userId);
                break;

            case 'addbot':
                $result = $coordinator->addBot($id, $userId);
                break;

            case 'ready':
                $coordinator->confirmDm($id, $userId);
                $result = ['toast' => 'lobby.ready_ok_toast', 'plans' => []];
                break;

            case 'begingame':
                [$plans, $toast] = $coordinator->start($id, $userId);
                $result = ['toast' => $toast, 'plans' => $plans];
                break;

            case 'kick': // host kicks payload userId from lobby (gameId slot = roomId)
                $result = $coordinator->kick($id, $userId, $payload);
                break;

            case 'n': // night action on seat payload
                $result = $coordinator->castNight($id, $userId, (int) $payload);
                break;

            case 'skipn':
                $result = $coordinator->skipNight($id, $userId);
                break;

            case 'v': // day vote on seat payload
                $result = $coordinator->castVote($id, $userId, (int) $payload);
                break;

            case 'abstain':
                $result = $coordinator->castVote($id, $userId, null);
                break;

            case 'pause':
                [$plans, $toast] = $coordinator->pause($id, $userId);
                $result = ['toast' => $toast, 'plans' => $plans];
                break;

            case 'resume':
                [$plans, $toast] = $coordinator->resume($id, $userId);
                $result = ['toast' => $toast, 'plans' => $plans];
                break;

            case 'ext': // GRP-8 host +30s extension
                $result = $coordinator->extendPhase($id, $userId);
                break;

            case 'again': // GRP-6 rematch on a finished game (T2-gated in group games)
                $gameChatId = $coordinator->store()->loadSnapshot($id)?->chatId;
                if ($this->isGroupChatId($gameChatId)
                    && ! $this->gameInitiateAllowed(
                        $this->chatSettings($botConfig, (int) $gameChatId),
                        $botConfig,
                        (int) $gameChatId,
                        (int) $dto->from->id,
                    )
                ) {
                    $result = ['toast' => 'errors.game_initiate_denied', 'plans' => []];
                    break;
                }
                $result = $coordinator->rematch($id, $userId);
                break;

            case 'sos': // GRP-9 emergency assembly
                [$plans, $toast] = $coordinator->emergencyAssembly($id, $userId);
                $result = ['toast' => $toast, 'plans' => $plans];
                break;

            case 'endearly': // GRP-7 host ends the game (confirmation step)
                [$plans, $toast] = $coordinator->endEarlyAsk($id, $userId);
                $result = ['toast' => $toast, 'plans' => $plans];
                break;

            case 'endearlygo':
                [$plans, $toast] = $coordinator->endEarlyGo($id, $userId);
                $result = ['toast' => $toast, 'plans' => $plans];
                break;

            case 'dayshot': // sniper/bandit daytime shot on seat payload
                $result = $coordinator->dayShot($id, $userId, (int) $payload);
                break;

            case 'dayshotcancel':
                $result = ['toast' => null, 'plans' => []];
                break;

            case 'rules': // I18N-5 paginated wiki (gameId slot = page index)
                $lang = new LangPack($coordinator->localeFor($userId), $coordinator->langPath());
                $wiki = new RulesWiki($lang, (string) ($dto->message?->chat->id ?? $userId));
                $result = ['toast' => null, 'plans' => [$wiki->page(max(0, min($wiki->pageCount() - 1, (int) $id)))]];
                break;

            case 'rolepage': // ONB-2 encyclopedia (gameId slot = role id)
                $lang = new LangPack($coordinator->localeFor($userId), $coordinator->langPath());
                $encyclopedia = new RoleEncyclopedia($lang);
                $result = ['toast' => null, 'plans' => [$encyclopedia->page($id)]];
                break;

            case 'lang': // ONB-1 persist the preference and re-render the welcome card in the new locale
                if (! LocaleResolver::isValid($id)) {
                    $result = ['toast' => null, 'plans' => []];
                    break;
                }
                $coordinator->profiles()->setPreferredLocale($userId, $id);
                $card = new WelcomeCard(
                    new LangPack($id, $coordinator->langPath()),
                    (string) ($dto->message?->chat->id ?? $userId),
                    $id,
                );
                $result = ['toast' => 'onb.lang_set', 'plans' => [$card->card()]];
                break;

            case 'onbsoon': // ONB-1 W5 placeholder buttons (rooms / training only)
                $result = ['toast' => 'onb.coming_soon', 'plans' => []];
                break;

            case 'qpjoin': // quickplay queue join
                $result = $coordinator->joinQuickplay($botConfig->botId, $userId, $name);
                break;

            case 'qpcancel': // quickplay queue cancel
                $result = $coordinator->cancelQuickplay($botConfig->botId, $userId);
                break;

            default:
                $result = ['toast' => 'errors.stale_action_toast', 'plans' => []];
        }

        // Always answer the callback query (toast or silent acknowledgment)
        $toastKey = $result['toast'] ?? null;
        $toastText = $toastKey !== null ? $this->langForCallback($toastKey, $coordinator, $userId) : null;
        $this->sender->send($botConfig, new AnswerCallbackQueryMethodDTO(
            callbackQueryId: $dto->id,
            text: $toastText,
        ));

        // Attach callbackQueryId to all plans so presenters can chain answers
        $plans = array_map(
            fn (SendPlan $plan) => new SendPlan(
                chatId: $plan->chatId,
                text: $plan->text,
                keyboard: $plan->keyboard,
                silent: $plan->silent,
                editMessageId: $plan->editMessageId,
                toast: $plan->toast,
                callbackQueryId: $dto->id,
            ),
            $result['plans'] ?? [],
        );
        $this->sendPlans($plans, $botConfig);
    }

    /** Telegram group/supergroup ids are negative; interface games carry null. */
    private function isGroupChatId(?string $chatId): bool
    {
        return $chatId !== null && is_numeric($chatId) && (int) $chatId < 0;
    }

    private function pushToDlq(TgBotConfig $botConfig, string $callbackData, string $userId, \Throwable $e): void
    {
        try {
            $dlq = app(MafiaDlqContract::class);
            $dlq->push($botConfig->botId, [
                'callbackData' => $callbackData,
                'botId' => $botConfig->botId,
                'userId' => $userId,
                'exception' => $e->getMessage(),
                'failedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c'),
            ]);
        } catch (\Throwable $dlqError) {
            Log::error('mafia.dlq.push_failed', [
                'botId' => $botConfig->botId,
                'error' => $dlqError->getMessage(),
            ]);
        }
    }

    /** @param  array<string, mixed>  $coordinator */
    private function langForCallback(string $key, GameCoordinator $coordinator, string $userId): string
    {
        $locale = $coordinator->localeFor($userId);
        $lang = $coordinator->lang($locale);

        return $lang->t($key, escape: false);
    }

    public function onException(ProcessorErrorContext $context): void
    {
    }
}
