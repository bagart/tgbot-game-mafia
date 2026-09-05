# OPS-07 — Soak Testing

Status: TODO
Depends: OPS-06

## Goal
≥2 h soak: bounded memory, Redis key count returns to baseline (no residue from snapshots,
locks, notes TTLs, idempotency keys), stable p95, no leaked long polls.

## Sources
- todo.mafia.md OPS-3 soak clause

## Acceptance
- [ ] Baseline-restoration assertion automated
