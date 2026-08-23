#!/usr/bin/env bash
set -e
PKG=/home/bagart/code/telegram-bot-module-mafia
cd "$PKG"
git init -b main
echo "toplevel: $(git rev-parse --show-toplevel)"
git add -A
echo "staged files: $(git diff --cached --name-only | wc -l)"
echo "outside-package staged: $(git diff --cached --name-only | grep -c '^\\.\\./' || true)"
git -c user.name="BAGArt" -c user.email="baltaev.artur+ask@gmail.com" commit -q -m "feat: mafia module MVP skeleton

- Pure core: 16-role catalog (resources/roles.json), count-based preset
  builder with checkbox filtering + constraint validation, night resolution
  order, vote tally (tie -> revote -> repeated tie = nobody), win conditions
  incl. satanist sacrifice and solo last-standing
- Rooms: interface/group kinds, private/public visibility, join guards,
  DM ready-check gate on start
- AI filler players: nickname factory, persona speech packs, heuristic brain
  behind fairness-firewall contract (views-only)
- i18n: ru/en/zh/es JSON packs, CLDR plurals, escaping, key-parity test
- Presentation: pure renderers -> SendPlan lists (group + interface skins)
- Telegram seam: TgModuleContract entry (/play, /kick, callback router,
  group-message mirror processor)
- In-memory stores behind contracts; migrations ship in-package
- Pest suite: 30 tests green; pint clean"
git log --oneline
