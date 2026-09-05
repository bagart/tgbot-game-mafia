<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Tests\Support;

use BAGArt\TelegramBotMafia\Bots\HeuristicBrain;
use BAGArt\TelegramBotMafia\GameCoordinator;
use BAGArt\TelegramBotMafia\Rooms\RoomService;
use BAGArt\TelegramBotMafia\Settings\MafiaSettings;
use BAGArt\TelegramBotMafia\State\InMemoryMafiaStateStore;
use BAGArt\TelegramBotMafia\State\InMemoryProfileStore;
use BAGArt\TelegramBotMafia\State\InMemoryRoomRepository;

/** Builds a fully wired coordinator over in-memory stores + fake clock. */
final class CoordinatorFactory
{
    public static FakeClock $clock;

    public static function make(?MafiaSettings $settings = null, ?\Closure $random = null): GameCoordinator
    {
        $store = new InMemoryMafiaStateStore();
        $profiles = new InMemoryProfileStore();
        self::$clock = new FakeClock();
        $rooms = new RoomService(
            rooms: new InMemoryRoomRepository(),
            store: $store,
            profiles: $profiles,
            clock: self::$clock,
            settings: $settings ?? new MafiaSettings(),
            random: $random ?? static fn (int $max): int => random_int(0, $max),
        );

        return new GameCoordinator(
            rooms: $rooms,
            store: $store,
            profiles: $profiles,
            clock: self::$clock,
            langBasePath: dirname(__DIR__, 2).'/resources/lang',
            brain: new HeuristicBrain(fn (int $max): int => 0),
            settings: $settings ?? new MafiaSettings(),
        );
    }

    /** Fresh coordinator over the same stores — simulates a process restart. */
    public static function restartFrom(GameCoordinator $existing): GameCoordinator
    {
        return new GameCoordinator(
            rooms: $existing->rooms(),
            store: $existing->store(),
            profiles: $existing->profiles(),
            clock: self::$clock,
            langBasePath: dirname(__DIR__, 2).'/resources/lang',
            brain: new HeuristicBrain(fn (int $max): int => 0),
            settings: new MafiaSettings(),
        );
    }
}
