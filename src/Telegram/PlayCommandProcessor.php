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
use BAGArt\TelegramBotMafia\Config\MafiaDefaults;
use BAGArt\TelegramBotMafia\Presentation\SendPlan;

/**
 * "/play" — the single entry command in any context:
 * group → join/create the group lobby; private → interface menu hint.
 */
class PlayCommandProcessor implements TgModuleProcessorContract
{
    use SendsPlans;

    public const NAME = 'play';

    public static function moduleId(): string
    {
        return 'mafia';
    }

    public static function build(BotProcessorContext $context): self
    {
        $self = new self;
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

        $chatType = (string) ($dto->chat->type ?? 'private');
        $isGroup = in_array($chatType, ['group', 'supergroup'], true);
        $chatKey = (string) $dto->chat->id;
        $userId = (string) $dto->from->id;
        $name = $this->displayName($dto);

        if (! $isGroup) {
            $this->sendPlans([new SendPlan(
                $chatKey,
                $coordinator->lang('ru')->t('interface.main_menu_title', [], escape: false),
            )], $botConfig);

            return;
        }

        $room = $coordinator->rooms()->findByChat($chatKey, 'lobby')
            ?? $coordinator->rooms()->findByChat($chatKey, 'running');
        if ($room === null) {
            $newRoom = $coordinator->createRoom(
                kind: 'group',
                chatId: $chatKey,
                title: '',
                hostId: $userId,
                hostName: $name,
                min: MafiaDefaults::PLAYERS_MIN,
                max: MafiaDefaults::PLAYERS_MAX,
                checkedRoles: [],
                locale: 'ru',
            );
            $this->sendPlans([$coordinator->lobbyCard($newRoom)], $botConfig);

            return;
        }
        if ($room->status !== 'lobby') {
            return; // game running in this chat — joining closed
        }
        $result = $coordinator->join($room->id, $userId, $name);
        if (($result['toast'] ?? null) === 'interface.dm_required') {
            $this->sendPlans([new SendPlan(
                $chatKey,
                $coordinator->lang($room->locale)->t('interface.dm_required', ['name' => $name], escape: false),
            )], $botConfig);

            return;
        }
        $this->sendPlans($result['plans'], $botConfig);
    }

    public function onException(ProcessorErrorContext $context): void {}

    private function displayName(MessageTypeDTO $dto): string
    {
        $name = trim(($dto->from?->first_name ?? '').' '.($dto->from?->last_name ?? ''));

        return $name !== '' ? $name : (string) $dto->from?->id;
    }
}
