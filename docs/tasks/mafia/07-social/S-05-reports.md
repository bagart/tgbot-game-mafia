# S-05 — Reports & Karma

Status: TODO
Depends: S-04

## Goal
One-tap report on every public surface: last-N public events snapshot + room/user context →
moderation queue; per-user daily cap. Karma score with decay; low karma restricts hosting
public rooms (enforced in join guard). Anomaly flags (collusion/win-trading suspicion) land in
the queue only — never auto-punish.

## Sources
- todo.mafia.md MOD-1, DISC-8, ADV-2; draft body §43

## Acceptance
- [ ] Report lands with replayable context; cap enforced; privacy rules honored (OPS-09)
