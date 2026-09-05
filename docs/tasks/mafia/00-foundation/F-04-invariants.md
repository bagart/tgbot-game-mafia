# F-04 — Domain Invariants

Status: TODO
Depends: F-03

## Goal
Fix the invariant list BEFORE any API design. These invariants become the backbone of
contract tests (API-16) and arch tests (D-03).

## Minimum invariant set
1. One active game per user (per bot).
2. Seat unique within game.
3. Role composition satisfies catalog constraints (R-02).
4. Dead player cannot perform living actions.
5. Action belongs to current phase; stale-phase action rejected.
6. Duplicate action is idempotent (same key ⇒ same result, no double effect).
7. Vote target must be a valid seat; visibility decided server-side only.
8. Game state transitions are atomic under per-game lock.
9. Win condition checked after every terminal action.
10. Private information never enters public projection (roles, checks, notes, ghost chat).

## Sources
- todo.mafia.md RUN-1..2 (locks, atomic counters, dedupe keys `(user, game, phase, action)`,
  stale rejection via gameId+phaseNumber)
- todo.mafia.md §9 rules 2/7 (disputes from artifacts; private-rich-public-calm)
- interface-ux.md §15.3 (notes store user-scoped; firewall arch test)

## Acceptance
- [ ] Each invariant has: statement, enforcement layer (domain/lock/API/projection), test hint
- [ ] Mapped 1:1 to future contract-test names (listed in API-16)

Next: F-05 (parallel-safe with F-06)
