# C-08 — Reconnect & Offline Continuity

Status: TODO
Depends: API-07

## Goal
Connection lost ⇒ stale banner ⇒ retry with backoff ⇒ state GET with last rev ⇒ reconcile.
Offline = UI continuity, NOT gameplay authority: never offline-play. Telegram surfaces get the
«use the keyboard» hint variant linking back to chat.

## Sources
- todo.mafia.md WEB-8; interface-ux.md §13 resilience; draft body §48

## Acceptance
- [ ] Network killed mid-phase ⇒ recovery without duplicate actions (idempotency proves it)
