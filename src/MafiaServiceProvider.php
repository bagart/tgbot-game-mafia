<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia;

use BAGArt\TelegramBotMafia\Bots\HeuristicBrain;
use BAGArt\TelegramBotMafia\Contracts\ClockContract;
use BAGArt\TelegramBotMafia\Contracts\MafiaStateStoreContract;
use BAGArt\TelegramBotMafia\Contracts\ProfileStoreContract;
use BAGArt\TelegramBotMafia\Contracts\RoomRepositoryContract;
use BAGArt\TelegramBotMafia\Rooms\RoomService;
use BAGArt\TelegramBotMafia\Settings\MafiaSettingsService;
use BAGArt\TelegramBotMafia\State\InMemoryMafiaStateStore;
use BAGArt\TelegramBotMafia\State\InMemoryProfileStore;
use BAGArt\TelegramBotMafia\State\InMemoryRoomRepository;
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

        // Resolves ModuleSettingsContract lazily on first use; callers fall
        // back to package defaults when the platform binding is absent.
        $this->app->singleton(MafiaSettingsService::class);

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

        // §14.1 publish pipeline: verbatim copy of the built chunk dir into
        // public/vendor/menu-modules/mafia (tag consumed by cmd/deps or
        // deploy: vendor:publish --provider=... --tag=mafia-assets).
        $this->publishes([
            dirname(__DIR__).'/public/vendor/menu-modules/mafia' => public_path('vendor/menu-modules/mafia'),
        ], 'mafia-assets');
    }
}
