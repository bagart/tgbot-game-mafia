<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core;

/**
 * Static accessor over resources/roles.json — the single source of truth for
 * role mechanics, constraints and count-based presets.
 */
final class RoleCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $data = null;

    private const MAFIA_TEAM = ['mafia', 'godfather', 'turncoat'];

    private const SOLO_KILLERS = ['maniac', 'bandit'];

    public static function exists(string $roleId): bool
    {
        return isset(self::data()['roles'][$roleId]);
    }

    public static function team(string $roleId): string
    {
        return (string) (self::data()['roles'][$roleId]['team'] ?? 'town');
    }

    public static function emoji(string $roleId): string
    {
        return (string) (self::data()['roles'][$roleId]['emoji'] ?? '🙂');
    }

    public static function action(string $roleId): ?string
    {
        $a = self::data()['roles'][$roleId]['action'] ?? null;

        return $a === null ? null : (string) $a;
    }

    public static function maxPerGame(string $roleId): int
    {
        $max = self::data()['roles'][$roleId]['max_per_game'] ?? null;

        return $max === null ? PHP_INT_MAX : (int) $max;
    }

    public static function isMandatory(string $roleId): bool
    {
        return (bool) (self::data()['roles'][$roleId]['mandatory'] ?? false);
    }

    /** @return list<string> */
    public static function mandatory(): array
    {
        return array_values((array) self::constraints()['mandatory_roles']);
    }

    /** @return list<string> */
    public static function mafiaTeamIds(): array
    {
        return self::MAFIA_TEAM;
    }

    /** @return list<string> */
    public static function soloKillerIds(): array
    {
        return self::SOLO_KILLERS;
    }

    public static function isKillerRole(string $roleId): bool
    {
        return in_array($roleId, [...self::MAFIA_TEAM, ...self::SOLO_KILLERS], true);
    }

    /** @return array<string, mixed> */
    public static function constraints(): array
    {
        return (array) self::data()['constraints'];
    }

    /** @return list<string> */
    public static function presetsFor(int $playerCount): array
    {
        $presets = (array) self::data()['presets_by_player_count'];
        $key = (string) max((int) self::constraints()['players_min'], min($playerCount, (int) self::constraints()['players_max']));

        return array_values((array) ($presets[$key] ?? []));
    }

    /** Night resolution order straight from the catalog. */
    /** @return list<string> */
    public static function nightOrder(): array
    {
        return array_values((array) self::data()['night_resolution_order']);
    }

    /** @return array<string, mixed> */
    private static function data(): array
    {
        if (self::$data === null) {
            $path = __DIR__.'/../../resources/roles.json';
            self::$data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        }

        return self::$data;
    }

    /** Test seam. */
    public static function reset(): void
    {
        self::$data = null;
    }
}
