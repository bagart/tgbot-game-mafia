# TG-01 — Bot as API Client

Status: TODO
Depends: API-15

## Goal
Rebuild the Telegram Mafia bot as a REAL API client:
`Telegram Update → Telegram Adapter → MafiaApiClient → Mafia API → Game Engine`.
After migration the bot does NOT know how votes are calculated, roles resolve, win conditions
work, snapshots persist, or rating is computed — it only receives, translates, calls, renders.

## Module wiring (platform-side, unchanged mechanics)
package skeleton in `../../../..` · `TgModuleContract` entry ·
enablement tri-state failClosed · processors via registries · lazy constructors · boot
isolation. Settings surface (`settingsFor('mafia')`: timings, language, ballot mode, max bots,
kill-switches) preserved as adapter config feeding API calls.

## Sources
- todo.mafia.md §1, PLAT-1..5; draft body §25
- AGENTS.md module rules (path repo consumption)

## Acceptance
- [ ] Zero game-domain classes in bot codebase (arch test)
- [ ] Generated client is the only outbound dependency
