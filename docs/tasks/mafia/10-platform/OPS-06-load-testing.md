# OPS-06 — Load Testing

Status: TODO
Depends: OPS-01, OPS-02, OPS-03, OPS-04, OPS-05

## Goal
Nightly load suite: 5 concurrent 15-player simulated games (KPI floor) · mirror fan-out burst ·
long-poll storm · headless 15-player game under CI time budget (perf budget gate). Targets:
p95 action < 500 ms, zero lost actions.

## Sources
- todo.mafia.md OPS-3/OPS-5; KPI table in index.md

## Acceptance
- [ ] Suite wired in CI; budget breach fails the job
