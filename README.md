# telegram-bot-module-mafia

Mafia (Werewolf) game module for the [Telegram bot platform](../telegram-bot-platform).
Own repository by design: independent versioning, own Pest suite.

## Status

MVP skeleton implementing plan rev 6 (`docs/tasks/todo.mafia.md` in the platform repo):

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

```bash
# 1. clone side-by-side with the platform
git clone <this-repo> ~/code/telegram-bot-module-mafia

# 2. host composer.json -> repositories[]
{ "type": "path", "url": "../telegram-bot-module-mafia" }

# 3. from the platform root (WSL shell)
composer require bagart/telegram-bot-module-mafia:@dev

# 4. host config/telegram.php
$configTelegram['modules_providers'][] = \BAGArt\TelegramBotMafia\MafiaModule::class;

# 5. enable per bot/chat when needed
php artisan tg:module:enable mafia --bot=X [--chat=Y]
```

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
`docs/tasks/todo.mafia.md` phases.
