<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Support;

/**
 * Inline-keyboard callback payload codec: "m:{action}:{gameId}:{payload}".
 * Game context rides in the payload because CallbackQueryTypeDTO.message is
 * an unimplemented oneOf stub (no chat access).
 */
final class CallbackData
{
    private const PREFIX = 'm';

    private const MAX_BYTES = 64;

    public static function encode(string $action, string $gameId = '', string $payload = ''): string
    {
        $data = self::PREFIX.':'.$action.':'.$gameId.($payload !== '' ? ':'.$payload : '');
        if (strlen($data) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Callback data exceeds 64 bytes');
        }

        return $data;
    }

    /** @return array{action:string, gameId:string, payload:string}|null */
    public static function decode(?string $data): ?array
    {
        if ($data === null || ! str_starts_with($data, self::PREFIX.':')) {
            return null;
        }
        $parts = explode(':', $data, 4);
        if (count($parts) < 3) {
            return null;
        }

        return [
            'action' => $parts[1],
            'gameId' => $parts[2],
            'payload' => $parts[3] ?? '',
        ];
    }
}
