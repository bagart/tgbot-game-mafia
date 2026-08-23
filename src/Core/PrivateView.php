<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core;

/**
 * The actor's own private slice: role, teammates (mafia bloc only), and the
 * results of their own night checks. Everything a fair bot brain may use.
 *
 * @param  list<int>  $teammateSeats
 * @param  list<CheckResult>  $myChecks
 */
final readonly class PrivateView
{
    public function __construct(
        public int $mySeat,
        public string $role,
        public array $teammateSeats = [],
        public array $myChecks = [],
    ) {}
}

/** One of the actor's own check results. */
final readonly class CheckResult
{
    public function __construct(
        public int $targetSeat,
        /** 'mafia' | 'innocent' for detective; exact role id for journalist */
        public string $verdict,
    ) {}
}
