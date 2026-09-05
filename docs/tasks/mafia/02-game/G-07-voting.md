# G-07 — Voting Model

Status: TODO
Depends: G-06

## Goal
Canonical vote model, server-side visibility. Open ballot: live public tally. Secret-ballot
mode (room setting): counts hidden until close — decided by SERVER, never by the client UI.

## Semantics
one vote per alive player per vote · change allowed while open · abstain · tie ⇒ revote ⇒
no elimination · close conditions (all voted / ready-skip / timeout) · final matrix recorded
as dispute artifact.

## API exposure
current voting state · own vote · public tally (per visibility mode) · final who-voted-whom
matrix on game end (C-05 renders).

## Sources
- todo.mafia.md CORE-8, GRP-5, DISC-4; interface-ux.md §6/§11/§12.1 (bars = presentation)
- Draft body §36

## Acceptance
- [ ] Vote state machine incl. revote chain
- [ ] Visibility rules server-enforced + covered by API-16 tests
- [ ] Matrix persisted for end screen & disputes
