# G-04 — Seating & Shuffle

Status: TODO
Depends: G-03

## Goal
Seat assignment at deal: sequential by default; optional shuffle («перемешать места»)
to defeat seat-order meta-gaming. Seats are integers unique per game (F-04 #2); shuffle is
seeded (G-12) and recorded in the snapshot for replay.

## Sources
- todo.mafia.md GRP-11; playability.md friction row (seat-order meta)

## Acceptance
- [ ] Shuffle deterministic under fixed seed; verified in replay test
