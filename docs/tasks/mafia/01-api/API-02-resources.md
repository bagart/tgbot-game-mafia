# API-02 — Resource Model

Status: TODO
Depends: API-01, F-02

## Goal
Canonical REST resources for /v1. API describes GAME operations, never Telegram UI
operations (no `POST /doWhateverTelegramButtonDoes`).

## Resource sketch (verify & finalize)
```
GET/POST /v1/rooms            GET /v1/rooms/{roomId}
POST     /v1/rooms/{roomId}/join|leave|start|kick
GET      /v1/rooms/templates                 (G-02)
GET      /v1/games/{gameId}                  GET /v1/games/{gameId}/state?rev=
POST     /v1/games/{gameId}/actions          GET /v1/games/{gameId}/events
GET      /v1/games/{gameId}/result|votes     GET/PUT/DELETE /v1/games/{gameId}/notes[/{seat}]
GET      /v1/games/{gameId}/ghost/feed       POST .../ghost/messages|predictions
GET      /v1/me  /v1/me/profile               GET /v1/roles  (safe metadata only)
GET      /v1/statistics /v1/leaderboard      POST /v1/reports   POST /v1/quickplay/match
```

Review mandate (Revision-2 §7): do NOT accept this list blindly — re-review naming, nesting,
and whether Room/Game separation is clean (Room exists before start; Game appears after).
`GET /games/{id}/replay|stats`: stats → P-02; replay endpoint RESERVED but non-public in v1
(access = moderation/admin; G-14 access matrix).

## Sources
- _refactor/migration-plan.md §3 of draft body (§8 resource list)
- F-02 entity list; G-task endpoint needs (G-09 ghost, G-10 notes)
- Revision-2 meta-prompt §7; `_refactor/architecture-review.md`

## Acceptance
- [ ] Every resource mapped to owning domain entity; no Telegram-specific fields
- [ ] Operation IDs reserved for OpenAPI (API-14 consumes this list)
- [ ] Room templates & ghost & notes resources included from day one
- [ ] Room-vs-Game lifecycle boundary explicitly stated

Next: API-03..API-06 (parallelizable)
