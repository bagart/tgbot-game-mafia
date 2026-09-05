<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Console;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SetChatMenuButtonMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SetMyCommandsMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SetMyDescriptionMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SetMyShortDescriptionMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\BotCommandTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MenuButtonCommandsTypeDTO;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ONB-3 bot profile packaging: presents a freshly connected bot properly —
 * user-facing commands, localized short/long descriptions and the commands
 * menu button. Best-effort per step: one failure never aborts the run.
 */
final class MafiaPackageCommand extends Command
{
    protected $signature = 'mafia:package
        {--bot= : package a single bot_id instead of all bots known to the platform}
        {--locale=* : locale or comma-separated list (default: en,ru)}
        {--all-locales : apply every supported locale (en, ru, es, zh)}';

    protected $description = 'Package the mafia bot profile: commands, localized descriptions, commands menu button';

    public function handle(): int
    {
        $locales = (bool) $this->option('all-locales')
            ? BotPackaging::supportedLocales()
            : BotPackaging::resolveLocales((array) $this->option('locale'));

        $targets = $this->targetBots();
        if ($targets === []) {
            $this->warn('no bots found to package'.($this->option('bot') !== null ? ' (bot "'.$this->option('bot').'" is unknown)' : ''));

            return $this->option('bot') !== null ? self::FAILURE : self::SUCCESS;
        }

        $sender = app(TgSenderContract::class);
        $failedBots = 0;

        foreach ($targets as $botId) {
            $config = $this->botConfig($botId);
            if ($config === null) {
                $this->warn("{$botId}: SKIP — no usable token in tg_bots");
                $failedBots++;

                continue;
            }

            [$ok, $total] = $this->applyToBot($sender, $config, $locales);
            $status = $ok === $total ? 'OK' : ($ok === 0 ? 'FAILED' : 'PARTIAL');
            $this->line("{$botId}: {$status} ({$ok}/{$total} steps)");
            if ($ok === 0) {
                $failedBots++;
            }
        }

        $this->info('mafia:package done: '.(count($targets) - $failedBots).'/'.count($targets)." bots packaged, locales: ".implode(',', $locales));

        return $failedBots === count($targets) ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<string> bot ids */
    private function targetBots(): array
    {
        $query = DB::table('tg_bots')->select('bot_id')->orderBy('bot_id');
        if ($this->option('bot') !== null) {
            $query->where('bot_id', (string) $this->option('bot'));
        }

        /** @var list<string> */
        return array_map(static fn (object $row): string => (string) $row->bot_id, $query->get()->all());
    }

    private function botConfig(string $botId): ?TgBotConfig
    {
        $token = DB::table('tg_bots')->where('bot_id', $botId)->value('token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            return new TgBotConfig(token: $token, botId: $botId);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Runs every packaging step for one bot; each step is isolated.
     *
     * @param  list<string>  $locales
     * @return array{int, int} successful steps / total steps
     */
    private function applyToBot(TgSenderContract $sender, TgBotConfig $config, array $locales): array
    {
        $steps = [
            ['setMyCommands (default)', fn (): mixed => $sender->send($config, new SetMyCommandsMethodDTO(
                commands: $this->commandDtos('en'),
            ))],
        ];

        foreach ($locales as $locale) {
            if ($locale !== 'en' && BotPackaging::hasCommandTranslations($locale)) {
                $steps[] = ["setMyCommands [{$locale}]", fn (): mixed => $sender->send($config, new SetMyCommandsMethodDTO(
                    commands: $this->commandDtos($locale),
                    languageCode: $locale,
                ))];
            }

            $texts = BotPackaging::profileDescriptions($locale);
            if ($texts === null) {
                continue;
            }
            // The first resolved locale also becomes the default (fallback) profile.
            $languageCode = $locale === $locales[0] ? null : $locale;
            $steps[] = ["setMyShortDescription [{$locale}]", fn (): mixed => $sender->send($config, new SetMyShortDescriptionMethodDTO(
                shortDescription: $texts['short'],
                languageCode: $languageCode,
            ))];
            $steps[] = ["setMyDescription [{$locale}]", fn (): mixed => $sender->send($config, new SetMyDescriptionMethodDTO(
                description: $texts['long'],
                languageCode: $languageCode,
            ))];
        }

        $steps[] = ['setChatMenuButton (commands)', fn (): mixed => $sender->send($config, new SetChatMenuButtonMethodDTO(
            menuButton: new MenuButtonCommandsTypeDTO(),
        ))];

        $ok = 0;
        foreach ($steps as [$label, $step]) {
            try {
                $step();
                $this->line("  {$label}: OK");
                $ok++;
            } catch (\Throwable $e) {
                $this->line("  {$label}: FAIL — ".$e->getMessage());
            }
        }

        return [$ok, count($steps)];
    }

    /**
     * @return list<BotCommandTypeDTO>
     */
    private function commandDtos(string $locale): array
    {
        return array_map(
            static fn (array $c): BotCommandTypeDTO => new BotCommandTypeDTO(
                command: $c['command'],
                description: $c['description'],
            ),
            BotPackaging::commands($locale),
        );
    }
}
