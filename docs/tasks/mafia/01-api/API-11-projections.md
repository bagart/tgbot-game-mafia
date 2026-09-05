# API-11 — Public / Self / Private Projections

Status: TODO
Depends: API-02, F-04

## Goal
API NEVER returns a raw GameSnapshot. Projection layer is the security boundary.

## Projections
`public`: seats, alive/dead, public events, phase, timer, public votes.
`self`: own role, own actions, own permissions, own notes.
`private` (owner-scoped fragments): detective/journalist results, role-specific knowledge,
ghost state.

Response envelope for state endpoints (formalized in API-07): `{rev, notesRev, serverTime,
phase, deadlineAt, status, ruleset, public, self, private, notes, capabilities[]}` —
`capabilities` are server-computed affordances (type + allowed targets) for client UX;
they NEVER replace server-side authorization on POST.

## Sources
- todo.mafia.md CORE-9 (`PublicStateView` + `PrivateView`, arch-test enforced)
- interface-ux.md §12.1/§15 (HUD dots private-only; marks owner-visible)
- Draft body §13

## Acceptance
- [ ] Field-by-field projection matrix per role/state (which fragment goes where)
- [ ] Rule: private info of OTHER players never appears anywhere in any response
- [ ] Security test list drafted for API-16 (cross-user leak tests)

Next: API-16; R-05 consumes for bot views
