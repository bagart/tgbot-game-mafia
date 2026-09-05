# API-12 — Pagination

Status: TODO
Depends: API-02

## Goal
Cursor pagination for all collections (events, rooms list, leaderboard, reports queue).
Stable ordering keys; no offset-based paging on hot paths.

## Acceptance
- [ ] Cursor envelope schema (next/prev, limit, items)
- [ ] Per-resource ordering key table (events: seq; leaderboard: crowns+id tiebreak)
