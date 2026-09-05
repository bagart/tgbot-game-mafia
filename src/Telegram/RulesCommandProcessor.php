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
use BAGArt\TelegramBotMafia\Onboarding\RulesWiki;
use BAGArt\TelegramBotMafia\Presentation\RoleEncyclopedia;

/**
 * "/rules" and "/roles" — read-only wiki pages, work anywhere.
 */
class RulesCommandProcessor implements TgModuleProcessorContract
{
    use SendsPlans;

    public const NAME = 'rules';

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
            && in_array(TgCommandRegistry::parseCommandName($dto->text), [self::NAME, 'roles'], true);
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
        $command = TgCommandRegistry::parseCommandName((string) $dto->text);
        $lang = new LangPack($coordinator->localeFor((string) $dto->from->id), $coordinator->langPath());

        $plan = $command === 'roles'
            ? (new RoleEncyclopedia($lang))->index()
            : (new RulesWiki($lang, (string) $dto->chat->id))->firstPage();

        $this->sendPlans([$plan], $botConfig);
    }

    public function onException(ProcessorErrorContext $context): void
    {
    }
}
