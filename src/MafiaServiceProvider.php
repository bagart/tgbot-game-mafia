<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia;

use BAGArt\TelegramBotMafia\Bots\HeuristicBrain;
use BAGArt\TelegramBotMafia\Contracts\ClockContract;
use BAGArt\TelegramBotMafia\Contracts\MafiaStateStoreContract;
use BAGArt\TelegramBotMafia\Contracts\ProfileStoreContract;
use BAGArt\TelegramBotMafia\Contracts\RoomRepositoryContract;
use BAGArt\TelegramBotMafia\Rooms\RoomService;
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
 * Module discovery: self-registers MafiaModule::class into
 * config('telegram.modules_providers') (same contract as the other modules).
 */
final class MafiaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $providers = (array) Config::get('telegram.modules_providers', []);
        Config::set('telegram.modules_providers', array_values(array_unique(array_merge(
            $providers,
            [MafiaModule::class],
        ))));

        $this->app->singleton(ClockContract::class, SystemClock::class);
        $this->app->singleton(RoomRepositoryContract::class, InMemoryRoomRepository::class);
        $this->app->singleton(MafiaStateStoreContract::class, InMemoryMafiaStateStore::class);
        $this->app->singleton(ProfileStoreContract::class, InMemoryProfileStore::class);

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
                brain: new HeuristicBrain,
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        GameCoordinator::setInstance($this->app->make(GameCoordinator::class));
    }
}
