# P-03 — Rating: Facts Log & Crowns

Status: TODO
Depends: P-01

## Goal
Event-sourced rating: DB stores immutable FACTS (who sat with whom, team won, who left
mid-game, tier at deal). Crowns 👑 are a versioned PROJECTION computed by replay — never
authoritative. Formula v1: `Δ = K · tierFactor · (S − E)`, `E = 1/(1+10^((R_lobbyAvg−R_self)/400))`,
`K` decays with crown brackets, `tierFactor = 1 + 0.15·(tier−1)`; leaver forces S=0; floor 0;
anchor 100; provisional 5 games; all constants in settings.

Incentive properties (property tests): weak-lobby win ≈ +0 for top players · high-tier
amplifies stakes · leaver always penalized. Ranked eligibility per S-02. Idempotent projector
with `last_fact_seq` resume + `ratings:rebuild {season}` command (double rebuild byte-identical).

## Sources
- todo.mafia.md RAT-1..5 + §8; draft body §45

## Acceptance
- [ ] Append-only arch test on facts tables; formula property tests green
