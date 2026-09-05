# G-13 — Snapshots, Pause & Recovery

Status: TODO
Depends: F-02, API-06

## Goal
Active-game truth = readonly versioned snapshot in Redis; PG stores history only.
Recovery happens on the Game Service — a bot restart must never affect a running game.

## Snapshot fields
gameId · state · rev · phase · deadlineAt · pausedAt · ruleset {id, version} (R-06) ·
rngVersion · mirror flag · seed reference. Versioned deserialization (`fromJsonV1` pattern).

## Recovery semantics
lazy advance + scheduler fallback sweep for overdue deadlines; mirrors resume only if game
running; pause shifts `deadlineAt += pausedDuration`; actions rejected while paused.

## Sources
- todo.mafia.md RUN-1..3, RUN-6, §7 Redis keys; interface-ux.md §10
- Platform rules: Redis holds readonly DTOs only; lazy connections; flush on shutdown

## Acceptance
- [ ] Roundtrip serialize/deserialize stable + versioned
- [ ] Kill-process restart test: game resumes correctly, no lost/stuck phases
