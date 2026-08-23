# telegram-bot-mafia-module

Mafia (Werewolf) game module for the Telegram bot platform. Lives in `misc/BAGArt/`
together with the other libs/modules (own nested git repo); the host app consumes it
via composer — never through direct PSR-4 mappings (see AGENTS.md §Modules rule).

## Status

MVP skeleton implementing plan rev 6 (`../../../docs/tasks/mafia/todo.mafia.md` in the platform repo):

- **Pure core**: 16-role catalog (`resources/roles.json`), count-based preset builder with
  checkbox filtering + constraint validation, night resolution order (escort → doctor →
  bodyguard → kills → info roles), vote tally (tie → revote → second tie = nobody),
  win conditions (satanist sacrifice / mafia parity / all-killers-dead / solo last standing).
- **Rooms**: interface/group kinds, private/public visibility, join guards (capacity, started,
  freeze, one-active-game), bot fillers with unique nicknames.
- **Bots**: persona speech packs + heuristic brain behind a fairness-firewall contract
  (brains see filtered views only; arch-tested).
- **i18n**: ru/en/zh/es JSON packs, CLDR plurals, HTML-escaping interpolation, key-parity test.
- **Telegram seam**: `/play` lobby flow, callback router (night menus, votes), group-message
  mirroring into interface feeds, lazy deadline advance.
- **Persistence**: in-memory stores behind contracts (Redis/Eloquent swaps planned); migrations
  for rooms/members/games/profiles ship here and load via `MafiaServiceProvider`.

## Install into the host app

Already wired in this monorepo:

```bash
# host composer.json repositories[]:
{ "type": "path", "url": "misc/BAGArt/telegram-bot-mafia-module" }

# from platform root, WSL shell:
composer require bagart/telegram-bot-module-mafia:@dev

# host config/telegram.php:
$configTelegram['modules_providers'][] = \BAGArt\TelegramBotMafia\MafiaModule::class;

# enable per bot/chat when needed:
php artisan tg:module:enable mafia --bot=X [--chat=Y]
```

Rule of thumb (AGENTS.md §Modules rule): modules are developed in `misc/BAGArt/<name>-module/`
and connected only via `composer require` + path repository.

## Tests

```bash
composer install   # path-repo resolves bagart/telegram-bot-lib from the sibling platform
composer test
```

## Layout

```
src/Core/          pure engine (no I/O): snapshot, resolver, tally, win checks
src/Rooms/         room lifecycle + join guards
src/Bots/          filler players (nickname factory, personas, brain)
src/I18n/          lang-pack loader (plurals, escaping)
src/Presentation/  pure renderers → SendPlan lists (group + interface skins)
src/Telegram/      module processors (/play, /kick, callbacks, mirror)
src/State/         in-memory store implementations (contracts in src/Contracts)
resources/         roles.json + lang/<locale>/{ui,bot_players}.json
```

## Not yet implemented (tracked in the platform plan)

Redis snapshot store, Eloquent repositories, AnswerCallbackQuery toasts, editMessageText
live cards, ghost chat, quickplay, pause/resume UI, remaining Phase 2/3 features — see
`../../../docs/tasks/mafia/todo.mafia.md` phases.
