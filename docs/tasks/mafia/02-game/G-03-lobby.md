# G-03 — Lobby & Readiness

Status: TODO
Depends: G-01

## Goal
Lobby mechanics: join/leave, host controls, DM readiness gate, start guard.

## Rules
- Group humans join ONLY by explicit command (Telegram adapter maps `/play`); interface
  joins via buttons/deep links — both call the same API.
- DM gate: start blocked until every human confirmed their private channel works
  (`interface.dm_required` + ready-check card are Telegram renderings of one API state).
- Start guard: capacity ≥ min, all confirmed; roles dealt on start (G-05/R-03).
- Lobby card data served by API; presentation in TG-04.

## Sources
- todo.mafia.md GRP-1..3; interface-ux.md §5

## Acceptance
- [ ] Start-guard preconditions as API checks with codes
- [ ] Ready-check modeled domain-side (channel-agnostic), not as a Telegram concept
