# G-10 — Private Notes Overlay

Status: TODO
Depends: API-11

## Goal
Pencil marks + notes as a user-scoped overlay store OUTSIDE the game snapshot (resolves
contradiction C1): own `notesRev`, atomic per-user writes, no game lock, no public-rev bump —
toggling a mark never wakes other players.

## API
`GET /games/{id}/notes` · `PUT /games/{id}/notes/{seat}` · `DELETE /games/{id}/notes/{seat}`.
Auto marks (from own check results) are READ-ONLY, engine-written. Requesting another
player's notes must be impossible by design (owner-scoped keys).

## Palette v1
🔪 suspect · 🟢 clear · ❓ doubt · ⭐ my vote candidate (manual) · ✔️/❗️ auto check marks.
Stacking order auto-first; inline cap ≤3 per seat is PRESENTATION (C-02).
Lifecycle: editable while alive; wiped at end unless persisted to profile setting.

## Sources
- todo.mafia.md ROOM-13/20/21; interface-ux.md §15 (canonical §15.3 model wins over §11 text)
- Draft body §35

## Acceptance
- [ ] Store contract + Redis key scheme documented (`mafia:notes:{roomId}:{userId}` heritage)
- [ ] Firewall test: store API user-scoped; presenters never read another player's key
