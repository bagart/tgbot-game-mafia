<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Presentation;

use BAGArt\TelegramBotMafia\Support\CallbackData;

/** Inline keyboard row builders shared by presenters and processors. */
final class Keyboards
{
    /**
     * Seat grid: `cols` buttons per row, each firing `{prefix}` callback with
     * the seat as payload.
     *
     * @param  list<int>  $seats  @param  list<list<array{label: string, callback: string}>>  $appendRows
     * @return list<list<array{label: string, callback: string}>>
     */
    public static function seatGrid(string $action, string $gameId, array $seats, array $labelsBySeat = [], int $cols = 2, array $appendRows = []): array
    {
        $rows = [];
        $row = [];
        foreach ($seats as $seat) {
            $label = $labelsBySeat[$seat] ?? (string) $seat;
            $row[] = ['label' => $label, 'callback' => CallbackData::encode($action, $gameId, (string) $seat)];
            if (count($row) === $cols) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }

        return [...$rows, ...$appendRows];
    }

    /** @return list<list<array{label: string, callback: string}>> */
    public static function single(array $buttons): array
    {
        return [$buttons];
    }
}
