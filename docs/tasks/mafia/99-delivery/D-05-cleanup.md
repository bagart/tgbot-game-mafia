# D-05 — Cleanup Checklist (Phase L)

Status: TODO
Depends: all phases

## Goal
Final gate of the refactor (draft §68 + `_refactor/migration-matrix.md` A-05).

- [ ] Verify migration matrix: every row `done` (no content left unmapped)
- [ ] Verify no duplicate specifications remain across task files
- [ ] Verify OpenAPI coverage (API-14 DoD) and generated client builds (API-15)
- [ ] Delete legacy docs: todo.mafia.md · interface-ux.md · ui-patterns.md ·
      competitive-analysis.md · playability.md · mafia_persons.md
- [ ] Delete `_refactor/` folder entirely
- [ ] Update pointers: docs/INDEX.md rows, AGENTS.md mafia line (+ CLAUDE.md/GEMINI.md mirrors)
      → docs/tasks/mafia/index.md
- [ ] Final documentation lint (links, LF endings, English-only)
