# API-10 — Realtime Strategy

Status: TODO
Depends: API-07, API-09

## Goal
First canonical mechanism = rev + long polling. WebSocket/SSE NOT a prerequisite.
Architecture must allow REST polling → SSE → WebSocket later WITHOUT changing domain API.
Mobile push (APNS/FCM) is wakeup-only and never carries game state.

## Sources
- Draft body §16; interface-ux.md §13.4 (25 s hold / 5 s fallback)
- M-05 push contract

## Acceptance
- [ ] Upgrade path documented (what changes/what does not when SSE arrives)
- [ ] Push payload = event reference + gameId only
