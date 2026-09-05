# TG-02 — Group Adapter

Status: TODO
Depends: TG-01

## Goal
Group-specific mechanics live ENTIRELY in the Telegram adapter; the Mafia API knows
room/game/member — never a Telegram chat_id as domain identity (chat_id = integration
metadata on the room, G-01).

## Surface mapping
`/play` → room join/start command · `/mafia_status`, `/mafia_end`, `/kick` → admin ops ·
lobby card buttons → API calls · group vote board → projection rendering · DM verification
gate → G-03 readiness state rendered in Telegram terms.

## Sources
- todo.mafia.md GRP-1..8, §1 (command/processor registration stays module-side)
- interface-ux.md §5

## Acceptance
- [ ] Zero game rules in adapter code (arch test)
- [ ] Every button/callback maps to an API action type (R-04 table)
