<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Web;

use BAGArt\TelegramBotMenu\Contracts\TgWebUiContract;
use BAGArt\TelegramBotMenu\Manifest\TgWebUiManifest;
use BAGArt\TelegramBotMenu\Manifest\UiAudience;
use BAGArt\TelegramBotMenu\Manifest\UiEntry;
use BAGArt\TelegramBotMenu\Manifest\UiKind;

/**
 * Mafia menu manifest (task 18): chunk-track entry pointing at the built,
 * content-hashed bundle. Declared feature tokens ride the chunk handshake
 * itself (§14.1 v2.1) — the manifest carries only the url.
 */
final readonly class MafiaUi implements TgWebUiContract
{
    public static function manifest(): TgWebUiManifest
    {
        return new TgWebUiManifest(
            moduleId: 'mafia',
            title: 't:title',
            icon: '🕵️',
            kind: UiKind::Game,
            minAudience: UiAudience::User,
            entry: UiEntry::chunk(ChunkAsset::url()),
            sortKey: 'mafia',
        );
    }

    public static function translations(): array
    {
        return [
            'en' => ['title' => 'Mafia'],
            'ru' => ['title' => 'Мафия'],
            'es' => ['title' => 'Mafia'],
            'zh' => ['title' => '黑手党'],
        ];
    }
}
