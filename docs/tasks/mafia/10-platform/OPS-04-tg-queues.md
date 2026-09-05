# OPS-04 — Telegram Outbound Stays Platform-Side

Status: TODO
Depends: OPS-03

## Goal
Telegram outbound remains in the platform queue. Mafia API emits GameEvents; the Telegram
adapter consumes events and sends message/photo/edit/callback responses through the outbound
pipeline (rate limiting, batching, edit throttling owned by the pipeline). Mafia domain never
sees rate limits.

## Sources
- Draft body §55; todo.mafia.md §8; TG-06

## Acceptance
- [ ] No send calls from game engine (arch test); event consumer drives all sends
