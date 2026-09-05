# R-03 — Presets by Player Count

Status: TODO
Depends: R-02

## Goal
`presets_by_player_count` stays data-driven. Engine algorithm: take N → select preset → apply
enabled-role filters (host checkboxes) → validate constraints → deterministic deal (mandatory
first, then specials in preset order, rest civilians) → store resulting assignment.

Game stores the pinned ruleset identity (R-06) for replay/balance telemetry.

## Sources
- roles.json presets + $comment_presets; todo.mafia.md CORE-4, §4

## Acceptance
- [ ] Property tests N=5..15 incl. filtered subsets
- [ ] Versions recorded in snapshot/result (G-12/G-13 consume)
