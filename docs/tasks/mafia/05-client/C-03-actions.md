# C-03 — Action Screens UX

Status: TODO
Depends: API-08, C-01

## Goal
Client-agnostic action UX over the single actions endpoint: target pickers (role-scoped
candidates), confirm step before irreversible acts, cast feedback, change-choice affordance,
ready button, vote grid with live tally bars (width ∝ votes/max), emergency/+30s buttons where
budgets allow. Every screen carries gameId+phase for stale detection.

## Sources
- interface-ux.md §6/§12; R-04 action catalog

## Acceptance
- [ ] Each action type has a screen spec + disabled/hidden rules per phase/state
