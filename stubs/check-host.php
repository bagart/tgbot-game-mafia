<?php

declare(strict_types=1);

/*
 * Host-wiring verification (run from anywhere):
 *   php stubs/check-host.php
 * Boots the platform app and asserts module discovery sees Mafia.
 */

$platform = getenv('MAFIA_PLATFORM_DIR') ?: dirname(__DIR__, 2).'/telegram-bot-platform';

require $platform.'/vendor/autoload.php';

$app = require $platform.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

var_dump(class_exists(\BAGArt\TelegramBotMafia\MafiaModule::class));
echo 'modules_providers: '.json_encode(config('telegram.modules_providers'))."\n";

$registry = $app->make(\BAGArt\TelegramBot\Modules\TgModuleRegistry::class);
$bootloader = $app->make(\BAGArt\TelegramBot\Modules\ModuleBootloader::class);
$providers = array_merge(
    array_column((array) config('telegram.modules', []), 'provider'),
    (array) config('telegram.modules_providers', []),
);
$booted = $bootloader->bootAll(array_values(array_unique($providers)));
echo 'booted modules: '.json_encode($booted)."\n";
echo 'mafia registered: '.var_export($registry->has('mafia'), true)."\n";
