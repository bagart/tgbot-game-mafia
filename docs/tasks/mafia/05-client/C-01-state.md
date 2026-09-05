# C-01 — Client State Model

Status: TODO
Depends: API-07, API-11

## Goal
Canonical client state contract for ALL clients (WebApp/mobile/future):
`gameId · rev · publicState · selfState · privateFragments · pendingActions · connectionState`.
Server remains authoritative; the client NEVER mutates canonical game state locally.
UX principles that survive all surfaces: one primary action per screen; instant silent
feedback; card always current (edited in place, never re-sent on Telegram).

## Sources
- interface-ux.md §0/§6; draft body §46

## Acceptance
- [ ] State shape + update rules (reconciliation keyed by rev) written
