# TG-06 — Event→Message Presentation Pipeline

Status: TODO
Depends: API-09, TG-04

## Goal
Flow: Telegram group message → adapter → Mafia API/event ingress → audience projection →
Telegram outbound queue. Presentation decisions (message vs photo vs edit vs toast) live in
the presenter; the game engine never formats Telegram. Telegram rate limiting stays in the
platform pipeline — never leaks into Mafia domain.

## Sources
- todo.mafia.md §2/§8 (TgSenderContract owns limits; mirrors batched), ROOM-8
- Draft body §40/§55

## Acceptance
- [ ] No mirror logic inside game engine (arch test)
- [ ] Fan-out batching + edit throttling verified under load burst (OPS-04)
