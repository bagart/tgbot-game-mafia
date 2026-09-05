# OPS-01 — Horizontal Scaling & Partitioning

Status: TODO
Depends: F-01

## Goal
Game API horizontally scalable; NO game state in process memory. Authoritative state lives in
Redis/DB/durable event store per the persistence model (G-13). Workers stateless. Primary
concurrency key = gameId ⇒ N API instances, N workers, N Telegram bot instances.

## Deployment principle (Revision-2 §30–31)
Logical separation first, physical later. v1 ships ONE deployment: Laravel app + Mafia core +
API layer + Redis + queue. No Kubernetes/Kafka/gRPC/event-sourcing-everywhere until load
demands it. The contract must allow extracting Mafia API/Workers/Platform into separate
services later WITHOUT changing the public API (success criterion #15).

## Sources
- Draft body §52–53; todo.mafia.md §7/§8; `_refactor/architecture-review.md` PASS 1

## Acceptance
- [ ] Stateless-instance proof: any instance can serve any request (sticky-free)
- [ ] Extraction path documented (what moves, what stays, zero contract change)
