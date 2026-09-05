# G-12 — Fairness: Seeded RNG

Status: TODO
Depends: F-03

## Goal
Every game reproducible: `serverSeedCommit` (SHA-256) published before deal, `serverSeed`
revealed only after game end. Bot decisions also seeded and auditable. Any result replayable
from artifacts (design rule 2). Ruleset identity pinning lives in R-06; the formal
GameReplay model (immutable events, seq, deterministic re-simulation) lives in G-14 —
this task owns ONLY the RNG/commit-reveal mechanics.

## Sources
- todo.mafia.md CORE-7, ADV-1, §8; competitive-analysis P1 #9
- Revision-2 meta-prompt §15–16

## Acceptance
- [ ] Commit/reveal protocol spec + verification recipe for third parties
- [ ] Seeded bot decisions auditable (R-05 interplay)
