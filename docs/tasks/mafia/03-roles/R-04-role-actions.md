# R-04 — Canonical Action Types

Status: TODO
Depends: R-01

## Goal
Single catalog of action types consumed by API-08 (no ad-hoc button-shaped actions):
`night.heal` · `night.kill` · `night.check_alignment` · `night.check_role` · `night.block` ·
`night.protect` · `night.observe` · `night.self_heal` · `day.vote` · `day.ready` ·
`day.emergency` · `day.shot` (sniper/bandit) · `day.speech` (relay budget check) ·
`admin.pause|resume|kick|replace|end` (+ `phase.ready`, ghost messages/predictions).

Each action: type · actor · phase · target · payload · idempotency key scope · result shape.

## Sources
- Draft body §33; roles.json actions/scopes; todo.mafia.md CORE-5/8, GRP-7..9

## Acceptance
- [ ] Catalog table complete; every UI button in interface-ux.md maps to exactly one type
- [ ] Admin actions require moderation/host scope (API-03)
