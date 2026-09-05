<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Telegram;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Processing\Processors\TgModuleProcessorContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiTypeDTOContract;
use BAGArt\TelegramBot\Processing\BotProcessorContext;
use BAGArt\TelegramBot\Processing\ErrorHandling\ProcessorErrorContext;
use BAGArt\TelegramBot\TgApi\Types\DTO\CallbackQueryTypeDTO;
use BAGArt\TelegramBotMafia\I18n\LangPack;
use BAGArt\TelegramBotMafia\I18n\LocaleResolver;
use BAGArt\TelegramBotMafia\Onboarding\RulesWiki;
use BAGArt\TelegramBotMafia\Onboarding\WelcomeCard;
use BAGArt\TelegramBotMafia\Presentation\RoleEncyclopedia;
use BAGArt\TelegramBotMafia\Support\CallbackData;

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

        ['action' => $act, 'gameId' => $id, 'payload' => $payload] = $parsed;

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

            case 'again': // GRP-6 rematch on a finished game
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

            case 'onbsoon': // ONB-1 W5 placeholder buttons (quickplay / rooms / training)
                $result = ['toast' => 'onb.coming_soon', 'plans' => []];
                break;

            default:
                $result = ['toast' => 'errors.stale_action_toast', 'plans' => []];
        }

        // MVP delivery: plans only. Toasts land when the platform exposes an
        // AnswerCallbackQuery send path for modules.
        $this->sendPlans($result['plans'] ?? [], $botConfig);
    }

    public function onException(ProcessorErrorContext $context): void
    {
    }
}
