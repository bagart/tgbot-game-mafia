# R-02 — Composition Constraints

Status: TODO
Depends: R-01

## Goal
Constraint engine validating any role set before deal (from roles.json `constraints`):
players 5–15 · mafia ≥1 · mafia ≤ ⌊N/3⌋ · solo killers ≤2 · civilians ≥2 · mandatory
roles locked ON (mafia, detective) · per-role max/min.

## Sources
- roles.json constraints; todo.mafia.md CORE-4, GRP-3 (`rooms.roles_invalid_toast` → API error)

## Acceptance
- [ ] Property tests over N=5..15 for preset + custom checkbox sets
- [ ] Violations return reason-coded API errors (which rule failed)
