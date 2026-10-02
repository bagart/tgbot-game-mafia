<?php

declare(strict_types=1);

use BAGArt\TelegramBotAccess\AccessControlContract;
use BAGArt\TelegramBotManagement\Services\TelegramIdentityService;
use BAGArt\TelegramBotMafia\Auth\T2Gate;
use BAGArt\TelegramBotMafia\MafiaServiceProvider;

it('registers the T2 gate binding when the platform access + identity packages are loadable', function () {
    if (! class_exists(\Illuminate\Container\Container::class)
        || ! interface_exists(AccessControlContract::class)
        || ! class_exists(TelegramIdentityService::class)
    ) {
        $this->markTestSkipped('standalone package run: platform bindings are provided by the host app');
    }

    $container = new \Illuminate\Container\Container();
    (new MafiaServiceProvider($container))->register();

    expect($container->bound(T2Gate::class))->toBeTrue();
});

it('keeps the T2 gate unbound when the access contract is absent (legacy host)', function () {
    if (! class_exists(\Illuminate\Container\Container::class) || interface_exists(AccessControlContract::class)) {
        $this->markTestSkipped('access contract is loadable in this run');
    }

    $container = new \Illuminate\Container\Container();
    (new MafiaServiceProvider($container))->register();

    expect($container->bound(T2Gate::class))->toBeFalse();
});
