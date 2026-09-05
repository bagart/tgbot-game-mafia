# C-07 — Optimistic UI & Reconciliation

Status: TODO
Depends: API-06

## Goal
For WebApp/mobile: tap → optimistic UI → API → reconciliation by rev. For critical actions
(vote, role action, kill, heal) the SERVER RESPONSE ALWAYS WINS. Pending actions queue with
per-action idempotency keys; failed/stale optimistic entries roll back visually and refetch.

## Sources
- Draft body §47; interface-ux.md §15.2 (optimistic marks reconciled by notesRev)

## Acceptance
- [ ] Reconciliation rules per state slice (game rev vs notesRev)
