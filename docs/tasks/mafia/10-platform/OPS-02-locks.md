# OPS-02 — Locks & Atomicity Model

Status: TODO
Depends: OPS-01

## Goal
All game mutations for one game serialize under a per-game lock (`gameId`-scoped); different
games run concurrently. Atomic counters (Lua/increment) for votes/actions; idempotency keys
checked inside the lock; notes overlay deliberately OUTSIDE the game lock (single-writer path).

## Sources
- todo.mafia.md RUN-1..2, ROOM-20; draft body §52–53

## Acceptance
- [ ] Lock scope matrix (what locks what); deadlock-free ordering documented
