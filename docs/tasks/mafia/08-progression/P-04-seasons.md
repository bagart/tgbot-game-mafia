# P-04 — Seasons & Leaderboards

Status: TODO
Depends: P-03

## Goal
Season windows per bot; soft reset `crown' = anchor + (crown − anchor)/2`; weekly + season
boards; champion title. Provisional players render «?» until N ranked games.

## API
`GET /v1/leaderboard` · `GET /v1/me/rating` · `GET /v1/seasons/{id}` — rendering (👑 234,
🏆 Ранг N) belongs to clients.

## Sources
- todo.mafia.md RAT-6..8; interface-ux.md §14

## Acceptance
- [ ] Reset math unit-tested; boards match projection rebuild
