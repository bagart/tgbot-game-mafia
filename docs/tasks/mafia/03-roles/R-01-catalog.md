# R-01 — Role Catalog as Domain Config

Status: TODO
Depends: F-02

## Goal
`roles.json` stays the canonical DATA SOURCE but changes role: from UI documentation to
domain configuration. Fix the per-role schema: role id · team · action · scope · limits ·
immunities · win condition · detective-reads-as · bot_playable · constraints.

## Exposure rule
API `GET /v1/roles` returns SAFE metadata only (id, team, emoji, display-name key, night-order
position, tips) for the encyclopedia/wizard. Private role assignment NEVER derives from the
public catalog response.

## Sources
- roles.json (whole file — cite as data); todo.mafia.md §4, ONB-2 (consumer)
- Draft body §31

## Acceptance
- [ ] Schema documented; roles.json validated against it in CI (D-03)
- [ ] Safe-vs-private field split explicit
