# C-04 — Feed Rendering

Status: TODO
Depends: API-09

## Goal
Feed = append-only stream of public events (+ mirror entries, ghost items when dead).
Plain sends, never edited. Client decides presentation per event type: text line, event image
(MEDIA), spoiler-wrapped reveal micro-drama, reaction affordance (cosmetic only).

## Sources
- interface-ux.md §6 (feed discipline), §8 (images/captions), playability.md emotional design
- todo.mafia.md IMG-5 (reactions watchlist)

## Acceptance
- [ ] Event→presentation mapping table; zero flow dependencies on cosmetics
