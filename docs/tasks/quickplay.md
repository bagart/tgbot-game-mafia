# Mafia Quickplay Matchmaking

Ref: `docs/redesign-plan.md` §Quickplay. Scope: bot-scoped queue, auto-start at 5 players, 60s timeout, idempotent joins.

- [ ] `QuickplayQueueContract` + in-memory + Redis sorted-set implementations (`mafia:quickplay:{botId}`, score = wait timestamp, TTL)
- [ ] Coordinator flow: queue join (idempotent, returns position), cancel on leave/start; wire `m:onbsoon:quickplay` callback past the placeholder
- [ ] Auto-start: when a bot has 5+ queued players, create a group-less room and run `RoomService::start()`; deal plans to DMs
- [ ] Timeout: `mafia:sweep` extension drains expired entries (60s) and notifies queued players
- [ ] Tests: enqueue/enqueue-twice, threshold start, timeout drain, bot isolation, concurrent-join safety
- [ ] VERIFY: module suite green; SDD compression; update redesign-plan checklist; cleanup
