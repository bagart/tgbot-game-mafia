# R-06 — Ruleset Versioning

Status: TODO
Depends: R-03, G-12

## Goal
Consolidate scattered versioning (`rulesetVersion` G-13 · `presetVersion` R-03 · `rngVersion`
G-12) into ONE ruleset identity model: every game pins `ruleset {id: "classic", version: N}`
at deal. Old games continue to play/replay under their pinned version — NEVER re-resolved
against the current `roles.json`.

## Scope
- Catalog version vs preset version vs ruleset version: one composed identity or nested
  versions (decide + document).
- Storage in snapshot/result; exposure in the state envelope (API-07) and result payload.
- Compatibility policy: additive role fields vs behavior changes; when version bumps.
- Migration of in-flight games across a catalog deploy (none — pinned).

## Sources
- Revision-2 meta-prompt §15; `_refactor/architecture-review.md` PASS 3
- roles.json ($comment_presets); todo.mafia.md §4

## Acceptance
- [ ] Ruleset identity schema finalized and referenced by F-02/G-13/G-14/P-02
- [ ] Replay test proves old-version game replays identically after catalog update
