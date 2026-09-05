# Mafia — Architecture Review (Revision 2, API-First Amendment)

Status: COMPLETE (2026-08-24) · Supersedes conflicting wording in `_refactor/migration-plan.md`
and the original refactoring draft (its §19–20 "hand-written OpenAPI" clause).

Three-pass review per the Revision-2 meta-prompt. Findings below are binding for the plan.

## PASS 1 — Architecture

| Check | Finding | Resolution |
|---|---|---|
| Telegram-specific coupling in domain | Legacy: module processors reach game logic directly; presenters consume views (good) but the write path is callback-shaped | Single canonical action ingress (API-08) is mandatory; TG tasks become pure adapters (TG-01..06). Arch tests enforce |
| Duplicate business logic (keyboard vs WebApp paths) | Room wizard exists twice by design (keyboard chain + WebApp form) | Both are presentation; one API validates once (G-01/R-02). Parity checklist stays a TEST, not a second path |
| Game Core independent of presentation | Views/`rev`/`notesRev` heritage is sound — keep as-is | Preserved verbatim into API-11/API-06/G-10 (matrix maps every legacy decision to exactly one task) |
| Platform ownership | Mafia initially deploys INSIDE the platform process | Accepted with guardrail: logical boundary enforced by arch tests now; physical extraction later must not change the contract (OPS-01 amendment, §30–31 of meta-prompt) |

## PASS 2 — Contract

| Check | Finding | Resolution |
|---|---|---|
| Source of truth direction | Original draft said spec-first hand-written YAML; Revision-2 mandates code-first via `dedoc/scramble` + `scalar/laravel` | **AMENDED: code-first wins.** Hand-written YAML rejected as an inevitable second source of truth (drift). Contract artifact = Scramble-generated OpenAPI, snapshotted and diff-gated in CI. Trade-off documented in API-18 |
| Polymorphic actions (`oneOf` + discriminator over Action types) | Unknown whether Scramble expresses this cleanly from PHP DTOs | Blocking spike **API-18** before any schema work (API-14 depends on it). Fallback: explicit schema classes/annotations beside the DTOs — still single-source |
| Errors / auth / state / versioning | Legacy toast strings were UI-coupled | API-04 envelope gains stable machine `code` + `messageKey` pointing at client lang packs (reuses existing `ui.json` namespaces); adapters localize |
| Capabilities | Legacy clients computed affordances themselves («can I heal?») | State envelope carries server-computed `availableActions[]`; hints only — every POST re-validates server-side (API-07/API-08) |
| Mobile independence | Account was Telegram-bound | **Account aggregate introduced**: Account ← identities (telegram/apple/google/future), device sessions, tokens (API-03, F-02). Apple/Google sign-in implementation deferred; schema does not block them |

## PASS 3 — Plan

| Check | Finding | Resolution |
|---|---|---|
| Task granularity & deps | 97 small files beat the meta-prompt's example of ~21 medium files for context compaction (its own §26 goal) | Phased folder layout kept; dependency graph lives in index.md |
| Measurable Done | Stubs carry absorbed Done-when criteria from legacy registry | Kept; new tasks follow same format |
| Appendices/duplicates | Legacy docs frozen; deletion gated at D-05 | Unchanged |
| Contradiction found in own v1 plan | API-14 assumed hand-written `mafia-openapi.yaml` | Rewritten this revision (Scramble pipeline); API-15/16/17 amended accordingly |
| Ruleset pinning scattered | `rngVersion` (G-12), `presetVersion` (R-03), `rulesetVersion` (G-13) named inconsistently | Consolidated into new **R-06 ruleset-versioning** task: `ruleset {id, version}` pinned per game; old games replay under their pinned version, never against current catalog |
| Replay scattered | Seeded RNG + audit log + vote matrix lived in three places | Formalized as **G-14 GameReplay model** (immutable events, seq numbering, deterministic replay); public replay API deferred — moderation/admin scope first |
| Delta sync pressure | Meta-prompt floats `?since=` deltas | v1 ships full snapshot + rev long-poll (simplest correct); envelope + ordered event log designed so deltas/SSE can be added later without contract change (API-07 records the transport ladder) |

## Binding stack decisions

1. `dedoc/scramble` generates OpenAPI from Laravel controllers/form-requests/DTOs.
2. `scalar/laravel` serves the interactive reference — documentation UI only, never truth.
3. No hand-written OpenAPI YAML; no second API doc surface; no separate Telegram/WebApp/mobile contracts.
4. Custom schemas via explicit annotations/custom extensions living BESIDE the code they describe.
5. Contract stability = CI snapshot diff + response/request validation (API-16/17).
6. Single deployment initially (Laravel + Mafia core + API layer + Redis + queue); no K8s/Kafka/gRPC/event-sourcing-everywhere until load demands it.

## Deviations from the meta-prompt (with reasons)

1. **Delta sync deferred** (§11): snapshot+long-poll first; `since=` reserved. Deltas add cache-invalidation complexity before any client needs it.
2. **Replay endpoint not public in v1** (§16): model built now (G-14), exposure gated by privacy review (spectator lesson ADV-5).
3. **Apple/Google sign-in not implemented** (§14): identity-provider column ready; no provider code until mobile phase justifies it.
4. **File count**: ~97 tiny files instead of ~21 medium ones — better fits §26's limited-context execution requirement while keeping one concern per file.
