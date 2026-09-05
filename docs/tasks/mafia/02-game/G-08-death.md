# G-08 — Death & Elimination

Status: TODO
Depends: G-05

## Goal
Death handling as domain events: night deaths (morning reveal), day elimination, sniper/bandit
day shots, wills publication when the room preset enables it (default off).

## Rules
death triggers win-check immediately (F-03 terminal hook) · elimination → ghost eligibility
(G-09) · sleepy badge assignment on missed public vote while alive · morning result variants
(death/quiet night) as public events with owner-scoped role reveals at end only.

## Sources
- todo.mafia.md CORE-5/10, DISC-2, ROOM-19; interface-ux.md §8/§12.8

## Acceptance
- [ ] Event set for all death paths; no hidden info in public payloads pre-reveal
