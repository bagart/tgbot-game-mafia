<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core;

/**
 * Outcome of one resolved night. Pure data for presenters and coordinator.
 *
 * @param  list<int>  $deaths
 * @param  list<int>  $elderSaved
 * @param  array<string, array<string, array<string, string>>>  $checkResults
 */
final readonly class NightReport
{
    public function __construct(
        public array $deaths,
        public ?int $savedSeat,
        public array $elderSaved,
        public bool $satanistSacrificed,
        public ?string $witnessSeesName,
        public array $checkResults,
    ) {
    }
}
