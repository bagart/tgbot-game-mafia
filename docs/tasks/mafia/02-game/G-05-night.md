# G-05 — Night Resolution Engine

Status: TODO
Depends: R-01..R-04, F-03

## Goal
Deterministic night resolution in the fixed order from roles.json:
`escort → doctor → bodyguard → mafia_kill → maniac_kill → bandit_kill →
detective_check → journalist_check → witness_observe`.

## Edge cases (must be table-tested)
doctor self-heal 1×/game · elder first-night immunity · bomzh untargetable (heal wasted) ·
bodyguard dies-instead on attack · block/save/kill interactions per pair · godfather tiebreak
on mafia decision · results delivered as owner-scoped private fragments (API-11).

## Sources
- todo.mafia.md CORE-5, CORE-10 (16-role activation); roles.json night_resolution_order + notes
- interface-ux.md §6 night menus (presentation only)

## Acceptance
- [ ] Interaction matrix per role pair with expected outcomes
- [ ] Resolution reproducible from seed (G-12) and auditable via events (API-09)
