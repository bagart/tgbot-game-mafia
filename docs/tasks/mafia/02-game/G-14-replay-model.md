# G-14 — GameReplay Model

Status: TODO
Depends: G-11, G-12, R-06, API-09

## Goal
Formalize the existing fairness artifacts (seeded RNG, audit log, vote matrix) into a single
GameReplay model:
`{gameId, ruleset{id,version}, seed(+commit), eventSequence[], finalResult, metadata}`.

## Requirements
- Immutable events with per-game sequential numbering (API-09 envelope).
- Deterministic replay: same seed + events + ruleset ⇒ identical final result (property test).
- Privacy: replay contains hidden info → access scoped (moderation/admin first);
  PUBLIC replay endpoint deferred out of v1 (architecture-review deviation #2) — resource
  reserved in API-02, gated by privacy review (spectator lesson ADV-5).
- Export/retention/analytics hooks noted for P-02.

## Sources
- todo.mafia.md CORE-7, DISC-4, §8, ADV-1; Revision-2 meta-prompt §16

## Acceptance
- [ ] Model documented; replay test byte-identical
- [ ] Access matrix (who may read replay data at which phase)
