# API-08 — Action Endpoint & Ingress Pipeline

Status: TODO
Depends: API-05, API-06

## Goal
ONE endpoint for all game actions instead of dozens of button-shaped endpoints:
`POST /v1/games/{gameId}/actions`.

## Request
```json
{ "type": "night.heal", "phase": 12, "targetSeat": 4 }
{ "type": "day.vote",   "phase": 13, "targetSeat": 2 }
{ "type": "phase.ready","phase": 13 }
```
(+ `Idempotency-Key` header, optional `expectedRev`)

## Single ingress pipeline (order fixed)
authenticate → authorize → validate → stale-check → idempotency → game lock → domain
command → state transition → event → projection. WebApp/callback/mobile all use this one
path — no parallel business routes.

## Sources
- Draft body §9; todo.mafia.md WEB-3 (single write path), RUN-2 (dedupe/locks)
- R-04 action type catalog

## Acceptance
- [ ] Pipeline stage list with failure code at each stage (API-04 codes)
- [ ] Notes toggles explicitly routed AROUND the game lock (light single-writer path)
- [ ] Action type enum referenced from R-04 (no ad-hoc types)
