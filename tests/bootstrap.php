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
}

// composer autoload-dev does not cover the short Tests\ root used by Pest
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Tests\\Support\\')) {
        $path = __DIR__.'/Support/'.substr($class, strlen('Tests\\Support\\')).'.php';
        if (is_file($path)) {
            require $path;
        }
    }
});
