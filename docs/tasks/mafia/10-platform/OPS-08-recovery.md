# OPS-08 — Disaster Recovery

Status: TODO
Depends: OPS-01

## Goal
Recovery scenarios for Mafia state classes: Redis snapshot loss (active games), PG history
corruption (facts log!), event-store gaps. Aligns with platform `../../../../../../../docs/dr.md` RPO/RTO classes.
Ratings must be rebuildable from the facts log at any formula_version; games recover from
snapshots or end safely (never half-alive).

## Sources
- docs/dr.md (platform); todo.mafia.md RAT-5, RUN-3

## Acceptance
- [ ] Per-data-class RPO/RTO + drill steps documented and rehearsed once
