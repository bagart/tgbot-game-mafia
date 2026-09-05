# D-01 — DB Migrations Consolidation

Status: TODO
Depends: G-* (game tasks defining storage)

## Goal
Consolidate the legacy §7 schema draft into the final migration set for the service:
rooms/members · games/players/night_actions/votes (history) · profiles · seasons/ratings ·
facts-log additions (is_ranked, tier, season_id, avg_crowns_at_start, leave_status,
left_at_phase) · telemetry · bans. Append-only enforcement on facts tables via arch test.

## Sources
- todo.mafia.md §7 (SQL draft — migrate verbatim as starting point)

## Acceptance
- [ ] Migrations run clean; arch tests forbid UPDATE/DELETE on facts
