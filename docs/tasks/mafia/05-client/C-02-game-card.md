# C-02 — Game Card Composition

Status: TODO
Depends: C-01

## Goal
Client-agnostic Game Card contract composed from projections (never from events alone):
header (game · phase · timer chip coarse 15 s · day-progress chip) · roster rows with markers
(💀 dead, 😴 sleepy, 🤖 bot, ✅ ready, crown/tier badges) · own-role line · action row per
phase/role ("chosen ✓ tap to change" state) · inline marks cluster ≤3/seat (G-10 palette,
auto-first order).

## Sources
- interface-ux.md §6/§12.1/§15.2; todo.mafia.md GRP-5, design rule 6 (glanceability)

## Acceptance
- [ ] Card spec derivable from API-11 projections alone (no hidden info channels)
