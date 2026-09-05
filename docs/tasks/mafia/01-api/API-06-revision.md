# API-06 — Revision & Concurrency

Status: TODO
Depends: API-02

## Goal
Every game state carries monotonic `rev`. Clients send `expectedRev` on mutations; stale ⇒
`409 GAME_STATE_CHANGED`, client refetches. Private notes use separate `notesRev` so pencil
marks never bump public rev nor wake other players' long polls.

## Sources
- todo.mafia.md CORE-2 (monotonic rev), ROOM-20 (notes overlay own revision)
- interface-ux.md §13.4/§15.3; draft body §11
- Design rule: notes are scratchpad, not game state

## Acceptance
- [ ] Rev increment policy (which commits bump it) defined exactly
- [ ] notesRev contract separate store/endpoint (G-10 implements)
