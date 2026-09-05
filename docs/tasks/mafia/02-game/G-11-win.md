# G-11 — Win Conditions & Result

Status: TODO
Depends: G-08

## Goal
Win-check engine in fixed order (roles.json): `satanist_sacrificed_by_mafia →
mafia_parity_reached → all_killers_dead → solo_last_standing`. Runs after EVERY terminal
action (death, day shot). Produces the immutable Result.

## Result content
winner team/side, full role reveal, duration, days played, sleepy totals, per-player crown
delta when ranked (P-03), vote matrix reference, verification metadata (G-12).

## Sources
- todo.mafia.md CORE-6, GRP-6; roles.json win_conditions_engine_order

## Acceptance
- [ ] Each condition unit-tested incl. satanist sacrifice ordering
- [ ] Result event emitted once; game → FINISHED transition per F-03
