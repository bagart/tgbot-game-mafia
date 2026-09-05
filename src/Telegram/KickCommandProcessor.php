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
use BAGArt\TelegramBotMafia\Presentation\SendPlan;
use BAGArt\TelegramBotMafia\Support\CallbackData;

/**
 * "/kick {target}" — host-only, lobby-only removal via member picker.
 */
class KickCommandProcessor implements TgModuleProcessorContract
{
    use SendsPlans;

    public const NAME = 'kick';

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
        $chatKey = (string) $dto->chat->id;
        $room = $coordinator->rooms()->findByChat($chatKey, 'lobby');
        if ($room === null || $room->status !== 'lobby') {
            return;
        }
        $actorId = (string) $dto->from->id;
        if ($room->hostUserId !== $actorId) {
            return; // silent for non-hosts — no group noise
        }

        $lang = $coordinator->lang($room->locale);
        $rows = [];
        foreach ($coordinator->rooms()->activeMembers($room->id) as $member) {
            if ($member->userId === $actorId) {
                continue;
            }
            $rows[] = [[
                'label' => $lang->t('day.seat_button', ['seat' => '', 'name' => $member->name]),
                'callback' => CallbackData::encode('kick', $room->id, $member->userId),
            ]];
        }
        if ($rows === []) {
            return;
        }

        $this->sendPlans([new SendPlan(
            $chatKey,
            $lang->t('kick.menu_title', [], escape: false),
            $rows,
        )], $botConfig);
    }

    public function onException(ProcessorErrorContext $context): void
    {
    }
}
