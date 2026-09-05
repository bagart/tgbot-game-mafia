<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Telegram;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Processing\Processors\TgModuleProcessorContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiTypeDTOContract;
use BAGArt\TelegramBot\Processing\BotProcessorContext;
use BAGArt\TelegramBot\Processing\ErrorHandling\ProcessorErrorContext;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;

/**
 * Message processor with two jobs:
 * 1. mirror running-group-game messages into interface feeds (MirrorService role);
 * 2. lazy deadline enforcement on any group traffic of a live game.
 */
class MafiaMessageProcessor implements TgModuleProcessorContract
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
        return $dto instanceof MessageTypeDTO
            && $dto->text !== null
            && $dto->text !== ''
            && $dto->text[0] !== '/' // commands belong to command processors
            && ($dto->chat?->type ?? '') !== 'private';
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
        $chatKey = (string) $dto->chat->id;

        // lazy phase advance keeps timers honest even without cron
        $running = $coordinator->store()->gameByChat($chatKey);
        if ($running !== null) {
            $this->sendPlans($coordinator->advanceIfOverdue($running->gameId), $botConfig);
        }

        $authorName = trim(($dto->from->first_name ?? '').' '.($dto->from->last_name ?? ''));
        if ($authorName === '') {
            $authorName = (string) $dto->from->id;
        }
        $plans = $coordinator->mirrorGroupMessage($chatKey, $authorName, (string) $dto->text);
        if ($plans !== []) {
            $this->sendPlans($plans, $botConfig);
        }
    }

    public function onException(ProcessorErrorContext $context): void
    {
    }
}
