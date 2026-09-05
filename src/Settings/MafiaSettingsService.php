<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Settings;

use BAGArt\TelegramBot\Contracts\Modules\ModuleSettingsContract;
use BAGArt\TelegramBotMafia\MafiaModule;

/**
 * Laravel-facing reader over ModuleSettingsContract. Chat-level overrides
 * live in tg_module_enablements.module_settings; absent keys fall back to
 * package defaults inside MafiaSettings.
 */
class MafiaSettingsService
{
    public function __construct(
        private readonly ModuleSettingsContract $settings,
    ) {
    }

    public function get(string $botId, int $chatId): MafiaSettings
    {
        return MafiaSettings::fromArray(
            $this->settings->settingsFor(MafiaModule::ID, $botId, $chatId),
        );
    }
}
