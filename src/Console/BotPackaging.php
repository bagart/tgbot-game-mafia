<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Console;

/**
 * Pure payload builder for mafia:package (ONB-3). No I/O, no framework —
 * safe to unit-test and reuse if packaging moves behind a service later.
 *
 * @see https://core.telegram.org/bots/api#setmycommands
 * @see https://core.telegram.org/bots/api#setmydescription
 * @see https://core.telegram.org/bots/api#setmyshortdescription
 */
final class BotPackaging
{
    /** Applied when --locale/--all-locales are not given. */
    public const DEFAULT_LOCALES = ['en', 'ru'];

    private const COMMAND_ORDER = ['play', 'kick', 'rules', 'roles'];

    /**
     * User-facing command descriptions per locale (Bot API limit: 256 chars,
     * we keep ≤ 40 for tidy menus).
     *
     * @see https://core.telegram.org/bots/api#botcommand
     */
    private const COMMAND_DESCRIPTIONS = [
        'en' => [
            'play' => 'Start or join a Mafia game',
            'kick' => 'Remove a player from the lobby',
            'rules' => 'How to play Mafia',
            'roles' => 'All roles at a glance',
        ],
        'ru' => [
            'play' => 'Начать или присоединиться к игре',
            'kick' => 'Убрать игрока из лобби',
            'rules' => 'Как играть в Мафию',
            'roles' => 'Все роли игры',
        ],
    ];

    /**
     * Profile texts per locale: short (≤ 120 chars, shown in the bot list)
     * and long (≤ 512 chars, shown on the empty bot screen).
     *
     * @see https://core.telegram.org/bots/api#setmydescription
     */
    private const PROFILE_DESCRIPTIONS = [
        'en' => [
            'short' => 'Mafia (Werewolf) party game for group chats: night actions, day votes, AI bots fill empty seats.',
            'long' => 'Classic Mafia (Werewolf) for your group chat. Start a lobby with /play, invite friends or let AI bots fill the seats, then survive the night: mafia hunt, doctor saves, detective inspects. Day discussions and votes decide who is eliminated. The host controls kicks and settings; /rules explains everything, /roles lists all characters.',
        ],
        'ru' => [
            'short' => 'Игра в Мафию для групповых чатов: ночные действия, дневные голосования, боты занимают свободные места.',
            'long' => 'Классическая Мафия для вашего группового чата. Создайте лобби командой /play, пригласите друзей или заполните места ботами и переживите ночь: мафия убивает, доктор спасает, детектив проверяет. Днём обсуждения и голосование решают, кого исключить. Хост управляет киками и настройками; /rules объяснит правила, /roles покажет все роли.',
        ],
        'es' => [
            'short' => 'Juego de Mafia para grupos: acciones nocturnas, votaciones diarias y bots que completan los asientos.',
            'long' => 'Mafia clásica para tu grupo. Abre un lobby con /play, invita amigos o deja que los bots completen los asientos y sobrevive a la noche: la mafia mata, el médico salva, el detective investiga. De día, los votos deciden quién queda eliminado. /rules lo explica todo y /roles muestra todos los personajes.',
        ],
        'zh' => [
            'short' => '群聊版黑手党（狼人杀）：夜晚行动、白天投票，AI 机器人自动补位。',
            'long' => '经典黑手党（狼人杀）游戏。用 /play 开启房间，邀请朋友或让机器人补位。夜晚：杀手行凶、医生救人、侦探查验；白天讨论并投票淘汰嫌疑人。/rules 查看规则，/roles 查看全部角色。',
        ],
    ];

    /** @return list<string> */
    public static function supportedLocales(): array
    {
        return array_keys(self::PROFILE_DESCRIPTIONS);
    }

    /** @return list<array{command: string, description: string}> */
    public static function commands(?string $locale): array
    {
        if ($locale === null || ! isset(self::COMMAND_DESCRIPTIONS[$locale])) {
            return [];
        }

        $pack = self::COMMAND_DESCRIPTIONS[$locale];
        $result = [];
        foreach (self::COMMAND_ORDER as $name) {
            $result[] = ['command' => $name, 'description' => $pack[$name]];
        }

        return $result;
    }

    public static function hasCommandTranslations(string $locale): bool
    {
        return isset(self::COMMAND_DESCRIPTIONS[$locale]);
    }

    /**
     * Normalizes raw --locale option values (each entry may itself be a
     * comma-list); unknown locales are dropped, empty result falls back to
     * the defaults.
     *
     * @param  list<string>  $optionValues
     * @return list<string>
     */
    public static function resolveLocales(array $optionValues): array
    {
        $resolved = [];
        foreach ($optionValues as $value) {
            foreach (explode(',', (string) $value) as $locale) {
                $locale = strtolower(trim($locale));
                if ($locale !== '' && isset(self::PROFILE_DESCRIPTIONS[$locale])) {
                    $resolved[$locale] = true;
                }
            }
        }
        $resolved = array_keys($resolved);

        return $resolved === [] ? self::DEFAULT_LOCALES : $resolved;
    }

    /**
     * @return array{short: string, long: string}|null
     */
    public static function profileDescriptions(string $locale): ?array
    {
        return self::PROFILE_DESCRIPTIONS[$locale] ?? null;
    }
}
