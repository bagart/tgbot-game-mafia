<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Telegram;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Processing\Processors\TgModuleProcessorContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiTypeDTOContract;
use BAGArt\TelegramBot\Modules\TgCommandRegistry;
use BAGArt\TelegramBot\Processing\BotProcessorContext;
use BAGArt\TelegramBot\Processing\ErrorHandling\ProcessorErrorContext;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBotMafia\I18n\LangPack;
use BAGArt\TelegramBotMafia\Onboarding\WelcomeCard;

/**
 * "/start" — ONB-1 entry point in private chats: renders the welcome card
 * (rules + language switcher + W5 placeholders).
 */
class StartProcessor implements TgModuleProcessorContract
{
    use SendsPlans;

    public const NAME = 'start';

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
        return $dto instanceof MessageTypeDTO
            && $dto->text !== null
            && ($dto->chat?->type ?? '') === 'private'
            && TgCommandRegistry::parseCommandName($dto->text) === self::NAME;
    }

    public function isStrictOrdered(TgApiTypeDTOContract $dto, TgBotConfig $botConfig, ?string $action = null): bool
    {
        return false;
    }

    public function process(TgApiTypeDTOContract $dto, TgBotConfig $botConfig, ?string $action = null): void
    {
        if (! $dto instanceof MessageTypeDTO || $dto->chat === null || $dto->from === null) {
            return;
        }
        $coordinator = $this->coordinator();
        if ($coordinator === null) {
            return;
        }
        $userId = (string) $dto->from->id;
        $locale = $coordinator->localeFor($userId);
        $lang = new LangPack($locale, $coordinator->langPath());

        $this->sendPlans([(new WelcomeCard($lang, (string) $dto->chat->id, $locale))->card()], $botConfig);
    }

    public function onException(ProcessorErrorContext $context): void
    {
    }
}
