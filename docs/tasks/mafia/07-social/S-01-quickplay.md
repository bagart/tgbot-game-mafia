# S-01 — Quickplay Matchmaking

Status: TODO
Depends: G-01, S-02

## Goal
`POST /v1/quickplay/match`: server picks a compatible open public room (locale-matched).
Empty pool ⇒ bot-fill countdown with ETA (fill ≤30 s KPI). Bot-fill is SERVER-SIDE game
policy, not a Telegram feature. Training mode = special quickplay variant: instant all-bot
room, roles rotate, zero profile writes, exit anytime.

## Sources
- todo.mafia.md ROOM-10/14, BOT-5; playability.md friction rows 1–2

## Acceptance
- [ ] Matchmaking policy documented (compatibility keys, fill order, caps)
- [ ] Same endpoint serves Telegram/WebApp/mobile
