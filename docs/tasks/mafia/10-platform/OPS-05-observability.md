# OPS-05 — Observability: Ids, Metrics, Tracing

Status: TODO
Depends: API-01

## Goal
Every request/action carries: requestId · userId · gameId · roomId · clientId · idempotencyKey.
Metrics: API/action latency · stale actions · duplicate actions · game stalls · phase duration ·
queue depth · client disconnects · error rates. Tracing spans across mobile → API → engine →
event → Telegram. Alerting: DLQ depth, error rate, stalled-phase age, sweep failures →
throttled owner-chat alerts; health endpoint covers them.

## Sources
- Draft body §56; todo.mafia.md OPS-2

## Acceptance
- [ ] Metric list with labels; one throttled alert per induced failure (test)
