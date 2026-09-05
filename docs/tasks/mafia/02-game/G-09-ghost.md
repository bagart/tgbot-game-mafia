# G-09 — Ghost Chat & Predictions

Status: TODO
Depends: G-08, API-11

## Goal
Ghost chat as an API feature with audience rules enforced server-side (dead users only):
`GET ghost feed` · `POST ghost message` · `POST ghost prediction`. All clients get the same
model; nothing reaches the living until reveal.

## Predictions
dead players bet the expected winner; correct calls → «прозорливость» profile stat shown at
reveal. Dead stay busy, zero info risk.

## Sources
- todo.mafia.md ROOM-12; interface-ux.md §11 (ghost chat), §12.4 (predictions)
- Draft body §34

## Acceptance
- [ ] Audience filter server-enforced + API-16 isolation test
- [ ] Ghost feed integrated into event stream (API-09) without leaking to public projection
