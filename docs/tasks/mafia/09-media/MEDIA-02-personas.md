# MEDIA-02 — Persona Catalog & Asset Manifest

Status: TODO
Depends: —

## Goal
Rewrite `mafia_persons.md` content here as the asset-pipeline spec (it stops being an
architecture doc): concept (role = mechanic, persona = face; dealt independently) · unified
STYLE CORE + per-setting era layers · recognizability policy (no real-likeness) · licensing
rules for imported images · generation workflow & `build.php` deck tooling (`index.json` is
the machine-readable catalog — no runtime markdown parsing) · future settings roadmap.

## Data location
`personas/<setting>/` stays in place until module packaging moves assets into the package
resources. Room wizard gains a setting picker (default random); snapshot stores `setting_id`;
missing file ⇒ emoji fallback, never blocks a game.

## Sources
- mafia_persons.md §1–§10 (full migration target); todo.mafia.md IMG-4

## Acceptance
- [ ] This file fully supersedes mafia_persons.md (matrix row → done)
