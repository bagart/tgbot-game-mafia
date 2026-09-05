<?php

declare(strict_types=1);

/*
 * Standalone test bootstrap: prefer the package vendor dir; when absent
 * (host-style consumption), reuse the platform's autoload and map this
 * package's PSR-4 roots manually.
 */

$packageAutoload = dirname(__DIR__).'/vendor/autoload.php';
if (is_file($packageAutoload)) {
    require $packageAutoload;
} else {
    // tests/ -> module -> BAGArt -> misc -> platform root
    $candidates = [
        getenv('MAFIA_PLATFORM_VENDOR') ?: '',
        dirname(__DIR__, 4).'/vendor/autoload.php',
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate)) {
            require $candidate;

            break;
        }
    }

    spl_autoload_register(function (string $class): void {
        if (str_starts_with($class, 'BAGArt\\TelegramBotMafia\\')) {
            $path = dirname(__DIR__).'/src/'
                .str_replace('\\', '/', substr($class, strlen('BAGArt\\TelegramBotMafia\\'))).'.php';
            if (is_file($path)) {
                require $path;
            }
        }
    });

    // Menu-module contracts (chunk webUi/webApi surface, task 18) resolve
    // from the sibling repo when the host autoload is not in charge.
    spl_autoload_register(function (string $class): void {
        foreach (['BAGArt\\TelegramBotMenu\\' => '../telegram-bot-menu-module/src/'] as $prefix => $relative) {
            if (str_starts_with($class, $prefix)) {
                $path = dirname(__DIR__).'/'.$relative
                    .str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
                if (is_file($path)) {
                    require $path;
                }
            }
        }
    });
}

// Platform contracts beyond this package's own composer deps (menu-module
// chunk surface, telegram-bot-lib module contracts) resolve from the sibling
// repos. Registered unconditionally: spl_autoload only fires when composer
// autoload did not already cover the class.
spl_autoload_register(function (string $class): void {
    foreach ([
        'BAGArt\\TelegramBotMenu\\' => '../telegram-bot-menu-module/src/',
        'BAGArt\\TelegramBot\\Modules\\' => '../telegram-bot-lib/src/Modules/',
    ] as $prefix => $relative) {
        if (str_starts_with($class, $prefix)) {
            $path = dirname(__DIR__).'/'.$relative
                .str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($path)) {
                require $path;
            }
        }
    }
});

// composer autoload-dev covers BAGArt\TelegramBotMafia\Tests\ when the
// package vendor exists; keep a manual fallback for host-style consumption
// where this package's composer.json is not loaded.
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'BAGArt\\TelegramBotMafia\\Tests\\')) {
        $path = __DIR__.'/'
            .str_replace('\\', '/', substr($class, strlen('BAGArt\\TelegramBotMafia\\Tests\\'))).'.php';
        if (is_file($path)) {
            require $path;
        }
    }
});
