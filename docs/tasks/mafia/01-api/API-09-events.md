# API-09 — Canonical Domain Events

Status: TODO
Depends: F-03

## Goal
Game engine emits canonical events; events are NOT UI messages. Consumers decide rendering:
Telegram presenter (message/photo/edit/toast), mobile (native UI), stats/rating/analytics.

## Event set (starting point)
game.created · game.started · phase.started · player.ready · action.accepted · vote.cast ·
player.killed · phase.finished · game.finished (+ elimination detail, win, pause/resume,
host ops).

## Sources
- todo.mafia.md §2 (`GameEventContract` list), RUN-4 (event stream from manager)
- Draft body §15
- Platform convention: events feed async consumers (OPS-03); game transaction never waits

## Acceptance
- [ ] Event envelope schema: id, gameId, type, occurredAt, payload, seq
- [ ] Ordered per game (seq), delivery to async consumers specified
- [ ] Mapping table: legacy GameEventContract names → canonical event types

Next: API-10, OPS-03
