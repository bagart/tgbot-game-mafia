<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Web;

/**
 * Resolves the content-hashed chunk filename minted by the chunk build
 * (tools/build-chunk.mjs → resources/chunk/build-manifest.json). §14.1:
 * UiEntry::Chunk(url) references the hashed name verbatim — publish never
 * generates or renames. Pure PHP (INV-4): file read only, no Laravel types.
 */
final class ChunkAsset
{
    public const URL_BASE = '/vendor/menu-modules/mafia/';

    /**
     * @throws \RuntimeException when the build manifest is missing or malformed
     *       (the bundle was never built or shipped — a packaging error, not a
     *       runtime condition)
     */
    public static function file(): string
    {
        $raw = file_get_contents(dirname(__DIR__, 2).'/resources/chunk/build-manifest.json');

        if ($raw === false) {
            throw new \RuntimeException('mafia chunk build manifest missing — run `npm run build` in the mafia module');
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! is_string($decoded['file'] ?? null)) {
            throw new \RuntimeException('mafia chunk build manifest is malformed');
        }

        if (! preg_match('/^app\.[A-Za-z0-9_-]+\.js$/', $decoded['file']) || ! is_file(dirname(__DIR__, 2).'/public/vendor/menu-modules/mafia/'.$decoded['file'])) {
            throw new \RuntimeException("mafia chunk asset '{$decoded['file']}' is not present in the package public dir");
        }

        return $decoded['file'];
    }

    public static function url(): string
    {
        return self::URL_BASE.self::file();
    }
}
