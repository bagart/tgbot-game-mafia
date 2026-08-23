<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core;

use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;

/**
 * Public (shared) game slice — the ONLY state a bot brain may observe.
 *
 * @param  list<PublicSeat>  $seats
 * @param  list<int>  $teammateSeats  filled for mafia-bloc viewers only
 */
final readonly class PublicStateView
{
    public function __construct(
        public array $seats,
        public PhaseEnum $phase,
        public int $dayNumber,
        public array $teammateSeats = [],
    ) {}

    /** @param  list<SeatState>  $seatStates */
    public static function fromSeats(array $seatStates, PhaseEnum $phase, int $dayNumber, array $teammateSeats = []): self
    {
        return new self(
            seats: array_map(
                fn (SeatState $s) => new PublicSeat($s->seat, $s->name, $s->alive, $s->isBot),
                $seatStates
            ),
            phase: $phase,
            dayNumber: $dayNumber,
            teammateSeats: $teammateSeats,
        );
    }
}

/** One roster entry as the public sees it. */
final readonly class PublicSeat
{
    public function __construct(
        public int $seat,
        public string $name,
        public bool $alive,
        public bool $isBot,
    ) {}
}
