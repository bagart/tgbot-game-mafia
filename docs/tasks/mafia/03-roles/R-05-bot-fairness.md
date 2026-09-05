# R-05 — Bot Fairness Firewall

Status: TODO
Depends: R-04, API-11

## Goal
AI bot players consume PROJECTIONS ONLY — never the snapshot. Seeded decisions; speech never
references hidden info. Nicknames from locale pool with collision suffix; persona emoji;
replace-leaver inherits exactly the seat's knowledge state, nothing more.

## Sources
- todo.mafia.md CORE-9, BOT-1..6, §8 (view-based brains + arch test)
- Draft body §31 (bot_playable flag in catalog)

## Acceptance
- [ ] Arch test red when a brain touches anything beyond projections
- [ ] Determinism test under fixed seed
