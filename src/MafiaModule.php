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

/**
 * Mafia module entry point. Static by design: discovery reads metadata and
 * registers components without creating a stateful module instance.
 */
final class MafiaModule implements TgModuleContract
{
    public static function descriptor(): TgModuleDescriptor
    {
        return new TgModuleDescriptor(
            id: 'mafia',
            name: 'Mafia',
            version: '0.1.0',
            capabilities: [TgModuleCapability::Processor, TgModuleCapability::Command],
            defaultEnabled: false, // opt-in per bot/chat
            failClosed: true,      // enablement-storage error => disabled; actions never fire blind
        );
    }

    public static function register(TgModuleRegistrar $registrar): void
    {
        $registrar
            ->command(PlayCommandProcessor::NAME, PlayCommandProcessor::class)
            ->command(KickCommandProcessor::NAME, KickCommandProcessor::class)
            ->processor(MessageTypeDTO::class, MafiaMessageProcessor::class)
            ->processor(CallbackQueryTypeDTO::class, CallbackRouterProcessor::class);
    }
}
