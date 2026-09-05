<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\I18n;

/**
 * JSON lang-pack loader (ui.json + bot_players.json per locale).
 * Dot-path access, CLDR-category plurals, HTML-escaping interpolation and
 * seeded-random variant picking.
 */
final class LangPack
{
    /** @var array<string, array<string, mixed>> basePath|locale => [file => data] */
    private static array $cache = [];

    public function __construct(
        private readonly string $locale,
        private readonly string $basePath,
    ) {
    }

    /**
     * Translate a dot path. `$count` switches to plural-category selection
     * when the value is a {one,few,many,other} object.
     *
     * @param  array<string, string|int|float>  $replace
     */
    public function t(string $key, array $replace = [], ?int $count = null, bool $escape = true): string
    {
        $raw = $this->raw($key);
        if ($raw === null) {
            return $key;
        }
        if (is_array($raw)) {
            $raw = $this->pluralLine($raw, $count ?? (int) ($replace['count'] ?? 2));
        }
        $line = (string) $raw;

        foreach ($replace as $name => $value) {
            $line = str_replace(
                '{'.$name.'}',
                $escape ? htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') : (string) $value,
                $line
            );
        }

        return $line;
    }

    /** Pick a seeded-random variant from a bot_players speech category. */
    public function line(string $category, array $replace = [], ?int $seedIndex = null, bool $escape = true): string
    {
        $raw = $this->botRaw('speech.'.$category);
        if (! is_array($raw) || $raw === []) {
            return $category;
        }
        $i = ($seedIndex ?? random_int(0, count($raw) - 1)) % count($raw);

        return $this->interpolate((string) $raw[$i], $replace, $escape);
    }

    /** @return list<string> */
    public function namePool(): array
    {
        $pool = $this->botRaw('names.pool');

        return is_array($pool) ? array_values($pool) : [];
    }

    public function collisionSuffixFormat(): string
    {
        return (string) ($this->botRaw('names.collision_suffix_format') ?? '{base}_{n}');
    }

    /** @return list<string> flattened ui.json key paths */
    public function uiKeys(): array
    {
        return $this->flatten($this->file('ui'), '');
    }

    private function raw(string $key): mixed
    {
        // All interface strings live in ui.json; the dot path addresses nesting.
        $node = $this->file('ui');
        foreach (explode('.', $key) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        return $node;
    }

    private function botRaw(string $path): mixed
    {
        $node = $this->file('bot_players');
        foreach (explode('.', $path) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        return $node;
    }

    /** @return array<string, mixed> */
    private function file(string $name): array
    {
        $cacheKey = $this->basePath.'|'.$this->locale;
        if (! isset(self::$cache[$cacheKey][$name])) {
            $path = $this->basePath.'/'.$this->locale.'/'.$name.'.json';
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new \LogicException("Lang pack not found: {$path}");
            }
            self::$cache[$cacheKey][$name] = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        }

        return self::$cache[$cacheKey][$name];
    }

    /** @param array<string, string> $replace */
    private function interpolate(string $line, array $replace, bool $escape): string
    {
        foreach ($replace as $name => $value) {
            $line = str_replace(
                '{'.$name.'}',
                $escape ? htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') : (string) $value,
                $line
            );
        }

        return $line;
    }

    /**
     * CLDR plural category pick. ru: one/few/many/other; en+es: one/other;
     * zh: other only.
     *
     * @param  array<string, string>  $variants
     */
    private function pluralLine(array $variants, int $count): string
    {
        $cat = $this->pluralCategory($count);

        return (string) ($variants[$cat] ?? $variants['other'] ?? reset($variants));
    }

    private function pluralCategory(int $n): string
    {
        return match ($this->locale) {
            'zh' => 'other',
            'ru' => $this->ruCategory($n),
            default => $n === 1 ? 'one' : 'other',
        };
    }

    private function ruCategory(int $n): string
    {
        $mod100 = $n % 100;
        if ($mod100 >= 11 && $mod100 <= 14) {
            return 'many';
        }
        $mod10 = $n % 10;

        return match (true) {
            $mod10 === 1 => 'one',
            $mod10 >= 2 && $mod10 <= 4 => 'few',
            default => 'many',
        };
    }

    /** @return list<string> */
    private function flatten(array $node, string $prefix): array
    {
        $out = [];
        foreach ($node as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $out = [...$out, ...$this->flatten($value, $path)];
            } else {
                $out[] = $path;
            }
        }

        return $out;
    }
}
