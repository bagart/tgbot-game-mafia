# OPS-09 — Telemetry & Privacy

Status: TODO
Depends: OPS-05

## Goal
Anonymous event log (funnel /start → first game, drop-off per phase; KPI queries from
index.md). Aggregate-first: ids and counters only — never message content, never private-view
data. 90-day retention. `/privacy` text matches actual storage; `/delete_me` purges profile +
history rows for the user on that bot and honors telemetry exclusion flag.

## Sources
- todo.mafia.md OPS-1, MOD-4, §8 telemetry stance

## Acceptance
- [ ] Every KPI computable from live data without reading chats (test)
- [ ] Purge leaves zero rows for the user (test)
