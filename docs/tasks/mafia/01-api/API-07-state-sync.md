# API-07 — Canonical State Envelope & Sync Protocol

Status: TODO
Depends: API-06, API-11

## Goal
Two parts: (1) the canonical state ENVELOPE every client receives; (2) the sync protocol
that delivers it. Same mechanism serves Telegram WebApp, iOS, Android, future clients.
Telegram keyboard client receives fresh state inside each callback response (adapter-side).

## 1. State envelope (formal contract)
```
GET /v1/games/{gameId}/state
{
  "rev": 123, "notesRev": 45,
  "serverTime": "...", "phase": {...}, "deadlineAt": "...",
  "status": "running", "ruleset": {"id": "classic", "version": 7},
  "public": {...},            // seats, alive/dead, public events, timer, public votes
  "self": {...},              // own seat/role/actions/permissions
  "private": {...},           // owner-scoped fragments (checks, ghost state)
  "notes": {...},             // own overlay (G-10)
  "capabilities": [           // SERVER-computed affordances — hints only (meta-prompt §9)
    { "type": "night.heal", "targets": [1,2,4], "reason": null },
    { "type": "phase.ready" }
  ]
}
```
Capabilities never replace server-side authorization/validation: every POST re-validates.

## 2. Sync protocol
- `?since={revision}` parameter RESERVED now; v1 semantics: changed ⇒ `200 + full envelope`,
  unchanged ⇒ hold or empty. Deltas are NOT built in v1 (architecture-review deviation #1) —
  envelope + ordered event log keep them addable later without contract change.
- Long poll ~25 s hold on `rev` OR caller's own `notesRev` bump; plain-poll fallback ~5 s
  after two failed long polls; `204`-style no-change response shape defined.
- Mobile background→foreground recovery = refetch with last known rev (M-04).

## Transport ladder (recorded, not built)
full-snapshot polling → long polling (v1) → SSE/WebSocket later — transport carries NO game
semantics; events endpoint (API-09) is the future delta source.

## Sources
- todo.mafia.md WEB-2 / interface-ux.md §13.4 (heritage shape `{public, private, notes,
  rev, notesRev}`)
- Revision-2 meta-prompt §9–11; `_refactor/architecture-review.md`

## Acceptance
- [ ] Envelope schema finalized incl. capabilities + ruleset identity + serverTime
- [ ] Hold/fallback/no-change contracts specified; cost bounds noted (OPS-06 tests)
- [ ] Response = projections (API-11), never raw snapshot
- [ ] Privacy rule: hidden state never sent "hoping the client hides it"

Next: API-10
