<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia;

use BAGArt\TelegramBot\Modules\TgModuleCapability;
use BAGArt\TelegramBot\Modules\TgModuleContract;
use BAGArt\TelegramBot\Modules\TgModuleDescriptor;
use BAGArt\TelegramBot\Modules\TgModuleRegistrar;
use BAGArt\TelegramBot\TgApi\Types\DTO\CallbackQueryTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBotMafia\Telegram\CallbackRouterProcessor;
use BAGArt\TelegramBotMafia\Telegram\KickCommandProcessor;
use BAGArt\TelegramBotMafia\Telegram\MafiaMessageProcessor;
use BAGArt\TelegramBotMafia\Telegram\PlayCommandProcessor;
use BAGArt\TelegramBotMafia\Telegram\RulesCommandProcessor;
use BAGArt\TelegramBotMafia\Telegram\StartProcessor;
use BAGArt\TelegramBotMafia\Web\MafiaUi;
use BAGArt\TelegramBotMafia\Web\MafiaUiHandler;

/**
 * Mafia module entry point. Static by design: discovery reads metadata and
 * registers components without creating a stateful module instance.
 */
final class MafiaModule implements TgModuleContract
{
    public const ID = 'mafia';

    public static function descriptor(): TgModuleDescriptor
    {
        return new TgModuleDescriptor(
            id: self::ID,
            name: 'Mafia',
            version: '0.1.0',
            requiresModules: ['menu' => '*'],
            capabilities: [TgModuleCapability::Processor, TgModuleCapability::Command, TgModuleCapability::Ui],
            defaultEnabled: false, // opt-in per bot/chat
            failClosed: true,      // enablement-storage error => disabled; actions never fire blind
        );
    }

    public static function register(TgModuleRegistrar $registrar): void
    {
        $registrar
            ->command(PlayCommandProcessor::NAME, PlayCommandProcessor::class)
            ->command(KickCommandProcessor::NAME, KickCommandProcessor::class)
            ->command(RulesCommandProcessor::NAME, RulesCommandProcessor::class)
            ->command(StartProcessor::NAME, StartProcessor::class)
            ->processor(MessageTypeDTO::class, MafiaMessageProcessor::class)
            ->processor(CallbackQueryTypeDTO::class, CallbackRouterProcessor::class)
            ->webUi(MafiaUi::class)
            ->webApi(MafiaUiHandler::class);
    }
}
