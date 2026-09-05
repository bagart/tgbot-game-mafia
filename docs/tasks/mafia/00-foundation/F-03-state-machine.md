# F-03 — Game State Machine

Status: TODO
Depends: F-02

## Goal
Fix the canonical phase state machine. Resolves contradiction C2 (inventory.md): legacy
CORE-1 chain (`lobby→night→discussion→voting→end`) is too coarse vs the draft plan's full
chain and roles.json's need for mid-phase terminal checks (sniper day shot).

## Canonical chain (starting point to verify)
LOBBY → DEAL → NIGHT → MORNING → DISCUSSION → VOTING → RESOLUTION → WIN_CHECK → (NIGHT | END)
Plus orthogonal states: PAUSED · CANCELLED · FINISHED.

## Rules
Every transition: deterministic · idempotent · auditable · event-producing.
Clients can never set state directly — only actions/events move the machine.

## Sources
- todo.mafia.md CORE-1, RUN-4..6 (orchestration, ready-skip early close, pause/resume)
- interface-ux.md §11 (ready-skip rule: must-act roles never bypassed silently)
- roles.json `win_conditions_engine_order` (win-check runs after every terminal action;
  sniper day_shot → immediate win-check)
- _refactor/inventory.md C2

## Changes
- Full transition table (from → to → guard → emitted event).
- Terminal-action hooks (death/shot) that jump to WIN_CHECK without losing phase context.
- PAUSED semantics: deadlines frozen (`pausedAt`), resume shifts deadlines by pause duration.

## Acceptance
- [ ] Transition table complete incl. guards and events
- [ ] Early-close (ready-skip) modeled as transition guard, not a bypass
- [ ] Mid-phase win-check path defined; END reachable from any terminal check
- [ ] Illegal-transition policy stated (reject + error code)

Next: F-04
