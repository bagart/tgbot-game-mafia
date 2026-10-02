<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia;

use BAGArt\TelegramBotAccess\AccessControlContract;
use BAGArt\TelegramBotManagement\Services\TelegramIdentityService;
use BAGArt\TelegramBotMafia\Auth\T2Gate;
use BAGArt\TelegramBotMafia\Bots\HeuristicBrain;
use BAGArt\TelegramBotMafia\Contracts\ClockContract;
use BAGArt\TelegramBotMafia\Contracts\MafiaDlqContract;
use BAGArt\TelegramBotMafia\Contracts\MafiaMetricsContract;
use BAGArt\TelegramBotMafia\Contracts\MafiaStateStoreContract;
use BAGArt\TelegramBotMafia\Contracts\ProfileStoreContract;
use BAGArt\TelegramBotMafia\Contracts\QuickplayQueueContract;
use BAGArt\TelegramBotMafia\Contracts\RoomRepositoryContract;
use BAGArt\TelegramBotMafia\Rooms\RoomService;
use BAGArt\TelegramBotMafia\Settings\MafiaSettingsService;
use BAGArt\TelegramBotMafia\State\InMemoryMafiaDlq;
use BAGArt\TelegramBotMafia\State\InMemoryMafiaMetrics;
use BAGArt\TelegramBotMafia\State\InMemoryMafiaStateStore;
use BAGArt\TelegramBotMafia\State\InMemoryProfileStore;
use BAGArt\TelegramBotMafia\State\InMemoryQuickplayQueue;
use BAGArt\TelegramBotMafia\State\InMemoryRoomRepository;
use BAGArt\TelegramBotMafia\State\MafiaShutdownHandler;
use BAGArt\TelegramBotMafia\State\SystemClock;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel wiring. MVP binds in-memory stores; production swaps Redis/Eloquent
 * implementations behind the same contracts without touching the core.
 *
 */
final class MafiaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The mafia:sweep schedule is declared in config/tg_modules.php
        // (schedule) and registered by the module engine, with
        // schedule-overrides.php user overrides applied.
        $this->app->singleton(ClockContract::class, SystemClock::class);
        $this->app->singleton(RoomRepositoryContract::class, InMemoryRoomRepository::class);
        $this->app->singleton(MafiaStateStoreContract::class, InMemoryMafiaStateStore::class);
        $this->app->singleton(ProfileStoreContract::class, InMemoryProfileStore::class);
        $this->app->singleton(MafiaDlqContract::class, InMemoryMafiaDlq::class);
        $this->app->singleton(MafiaMetricsContract::class, InMemoryMafiaMetrics::class);
        $this->app->singleton(QuickplayQueueContract::class, function ($app) {
            return new InMemoryQuickplayQueue($app->make(ClockContract::class));
        });
        $this->app->singleton(MafiaShutdownHandler::class);

        // Resolves ModuleSettingsContract lazily on first use; callers fall
        // back to package defaults when the platform binding is absent.
        $this->app->singleton(MafiaSettingsService::class);

        // PLAT-D14: T2 game-initiate gate — bound only with the platform
        // access + identity packages present; without it processors keep
        // the legacy open behavior (SendsPlans::gate() returns null).
        // The access contract is an interface, hence interface_exists().
        if (interface_exists(AccessControlContract::class) && class_exists(TelegramIdentityService::class)) {
            $this->app->singleton(T2Gate::class, fn ($app): T2Gate => new T2Gate(
                access: $app->make(AccessControlContract::class),
                identities: $app->make(TelegramIdentityService::class),
            ));
        }

        $this->app->singleton(RoomService::class, function ($app) {
            return new RoomService(
                rooms: $app->make(RoomRepositoryContract::class),
                store: $app->make(MafiaStateStoreContract::class),
                profiles: $app->make(ProfileStoreContract::class),
                clock: $app->make(ClockContract::class),
            );
        });

        $this->app->singleton(GameCoordinator::class, function ($app) {
            return new GameCoordinator(
                rooms: $app->make(RoomService::class),
                store: $app->make(MafiaStateStoreContract::class),
                profiles: $app->make(ProfileStoreContract::class),
                clock: $app->make(ClockContract::class),
                langBasePath: dirname(__DIR__).'/resources/lang',
                brain: new HeuristicBrain(),
                shutdownHandler: $app->make(MafiaShutdownHandler::class),
                metrics: $app->make(MafiaMetricsContract::class),
                quickplayQueue: $app->make(QuickplayQueueContract::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->commands([
            \BAGArt\TelegramBotMafia\Console\MafiaSweepCommand::class,
            \BAGArt\TelegramBotMafia\Console\MafiaPackageCommand::class,
        ]);
        GameCoordinator::setInstance($this->app->make(GameCoordinator::class));

        // Register shutdown handler for graceful shutdown
        $shutdownHandler = $this->app->make(MafiaShutdownHandler::class);
        $shutdownHandler->register();

        // §14.1 publish pipeline: verbatim copy of the built chunk dir into
        // public/vendor/menu-modules/mafia (tag consumed by cmd/deps or
        // deploy: vendor:publish --provider=... --tag=mafia-assets).
        $this->publishes([
            dirname(__DIR__).'/public/vendor/menu-modules/mafia' => public_path('vendor/menu-modules/mafia'),
        ], 'mafia-assets');
    }
}
