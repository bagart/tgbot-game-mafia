# OPS-03 — Async Event Consumers

Status: TODO
Depends: API-09

## Goal
After a successful mutation: domain transition → event → async consumers (Telegram
notification, statistics, rating projector, analytics, moderation, audit, push). The game
transaction NEVER waits for consumers. Consumer contract: idempotent, ordered per game,
resumable from seq.

## Sources
- Draft body §54; P-03 projector; todo.mafia.md §8

## Acceptance
- [ ] Consumer list with delivery guarantees + failure handling (retry vs DLQ)
