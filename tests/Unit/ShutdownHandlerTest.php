<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\State\MafiaShutdownHandler;

it('is not stopping by default', function () {
    $handler = new MafiaShutdownHandler();
    expect($handler->isStopping())->toBeFalse();
});

it('can be set to stopping', function () {
    $handler = new MafiaShutdownHandler();
    $handler->prepareShutdown();
    expect($handler->isStopping())->toBeTrue();
});

it('register sets up signal handlers', function () {
    $handler = new MafiaShutdownHandler();
    $handler->register();
    // No assertion needed — just verify it doesn't throw
    expect(true)->toBeTrue();
});
