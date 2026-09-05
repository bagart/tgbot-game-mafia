# Mafia — Implementation Plan

> Single navigation hub. No detailed specs here — each task file is self-contained.
> Legacy docs (`todo.mafia.md`, `interface-ux.md`, `ui-patterns.md`,
> `competitive-analysis.md`, `playability.md`, `mafia_persons.md`) are FROZEN during the
> migration: do not edit them; new decisions land only in task files.
> Refactor charter & migration matrix: `_refactor/` (deleted at Phase L).
> **Revision 2 (API-first amendment)**: `_refactor/architecture-review.md` — binding.

## Architecture

```
iOS / Android / Telegram Bot / Telegram WebApp / future clients
                        │
                  Mafia API  /v1   (auth · commands · state · events)
                        │
                    Game Domain       (rooms · games · phases · roles · actions
                        │             votes · rules · results)
              Persistence / Events    (Redis active truth · PG history · event stream)

Contract pipeline:
Laravel code → Scramble → OpenAPI artifact → Scalar docs · contract tests ·
                                            generated clients · mobile contract
```

One game engine · one canonical contract · many equal clients — no client is privileged.
Telegram is a client, not the game. The OpenAPI artifact is GENERATED from the Laravel
implementation via Scramble (code-first, single source; hand-written YAML forbidden);
Scalar serves documentation UI only. Stability is enforced by CI snapshot diffs, not by
manual spec editing.

## Stack decisions (binding — see `_refactor/architecture-review.md`)

- `dedoc/scramble` generates the OpenAPI contract from code; explicit annotations/extensions
  live beside the code they describe (never a second source).
- `scalar/laravel` publishes the interactive reference.
- v1 sync = full snapshot + rev long-poll; `since=` deltas/SSE/WebSocket deferred by design.
- Idempotency: external UUID key + internal semantic key (two layers).
- Identity = Account aggregate with provider-linked identities (telegram/apple/google/future).
- Every game pins `ruleset {id, version}` (R-06); replay model G-14.
- Single deployment initially; logical separation first, physical later.

## Execution order

Foundation → API contract → Roles → Game engine → Telegram client → Generic client →
Social → Mobile → Progression → Media → Platform/Scale → Delivery/Cleanup.

A phase starts when its dependencies are DONE. Tasks inside a phase run in ID order.
One sanctioned exception: G-12 executes right after F-03 — R-06 folds its seeded-RNG
version into the pinned ruleset, so the fairness chain crosses the Roles/Game boundary
once by design (verified by `_refactor/verify.php`).

## Task registry

Status: `TODO` / `WIP` / `DONE`. Update this table after every completed task.

### Phase A — Inventory (DONE)

`_refactor/inventory.md`, `_refactor/migration-matrix.md` — complete.

### Phase F — Foundation (`00-foundation`)

| ID | Task | Depends | Status |
|---|---|---|---|
| F-01 | [Service boundaries & architectural firewall](00-foundation/F-01-boundaries.md) | — | TODO |
| F-02 | [Domain model & canonical entities](00-foundation/F-02-domain-model.md) | F-01 | TODO |
| F-03 | [Game state machine](00-foundation/F-03-state-machine.md) | F-02 | TODO |
| F-04 | [Domain invariants](00-foundation/F-04-invariants.md) | F-03 | TODO |
| F-05 | [Terminology & design rules](00-foundation/F-05-terminology.md) | F-01 | TODO |
| F-06 | [Architecture diagram & repo layout](00-foundation/F-06-diagram.md) | F-01..F-04 | TODO |

### Phase API — Contract (`01-api`)

| ID | Task | Depends | Status |
|---|---|---|---|
| API-01 | [API principles](01-api/API-01-principles.md) | F-01 | TODO |
| API-02 | [Resource model](01-api/API-02-resources.md) | API-01, F-02 | TODO |
| API-03 | [Accounts, identity providers & scopes](01-api/API-03-auth-scopes.md) | API-01, F-02 | TODO |
| API-04 | [Error contract](01-api/API-04-errors.md) | API-01 | TODO |
| API-05 | [Action idempotency (two layers)](01-api/API-05-idempotency.md) | API-02 | TODO |
| API-06 | [Revision & concurrency](01-api/API-06-revision.md) | API-02 | TODO |
| API-07 | [State envelope & sync protocol](01-api/API-07-state-sync.md) | API-06, API-11 | TODO |
| API-08 | [Action endpoint & ingress pipeline](01-api/API-08-actions.md) | API-05, API-06 | TODO |
| API-09 | [Canonical domain events](01-api/API-09-events.md) | F-03 | TODO |
| API-10 | [Realtime strategy](01-api/API-10-realtime.md) | API-07, API-09 | TODO |
| API-11 | [Public/self/private projections](01-api/API-11-projections.md) | API-02, F-04 | TODO |
| API-12 | [Pagination](01-api/API-12-pagination.md) | API-02 | TODO |
| API-13 | [Versioning policy](01-api/API-13-versioning.md) | API-01 | TODO |
| API-18 | [Scramble/Scalar stack spike](01-api/API-18-scramble-stack.md) | API-01 (blocks API-14) | TODO |
| API-14 | [OpenAPI generation pipeline (Scramble)](01-api/API-14-openapi.md) | API-01..API-13, API-18 | TODO |
| API-15 | [Generated clients](01-api/API-15-generated-clients.md) | API-14 | TODO |
| API-16 | [Contract tests incl. security](01-api/API-16-contract-tests.md) | API-14 | TODO |
| API-17 | [Breaking-change CI gate](01-api/API-17-breaking-ci.md) | API-15, API-16 | TODO |

### Phase G — Game domain (`02-game`)

| ID | Task | Depends | Status |
|---|---|---|---|
| G-01 | [Room entity, lifecycle & join guard](02-game/G-01-room.md) | F-02, API-02 | TODO |
| G-02 | [Room templates & presets import](02-game/G-02-room-templates.md) | G-01, R-03 | TODO |
| G-03 | [Lobby & readiness](02-game/G-03-lobby.md) | G-01 | TODO |
| G-04 | [Seating & shuffle](02-game/G-04-seating.md) | G-03 | TODO |
| G-05 | [Night resolution engine](02-game/G-05-night.md) | R-01..R-04, F-03 | TODO |
| G-06 | [Day: discussion, ready-skip, +30s, emergency](02-game/G-06-day.md) | G-05 | TODO |
| G-07 | [Voting model](02-game/G-07-voting.md) | G-06 | TODO |
| G-08 | [Death & elimination](02-game/G-08-death.md) | G-05 | TODO |
| G-09 | [Ghost chat & predictions](02-game/G-09-ghost.md) | G-08, API-11 | TODO |
| G-10 | [Private notes overlay](02-game/G-10-notes.md) | API-11 | TODO |
| G-11 | [Win conditions & result](02-game/G-11-win.md) | G-08 | TODO |
| G-12 | [Fairness: seeded RNG](02-game/G-12-fairness.md) | F-03 | TODO |
| G-13 | [Snapshots, pause & recovery](02-game/G-13-recovery.md) | F-02, API-06 | TODO |
| G-14 | [GameReplay model](02-game/G-14-replay-model.md) | G-11, G-12, R-06, API-09 | TODO |

### Phase R — Roles (`03-roles`)

| ID | Task | Depends | Status |
|---|---|---|---|
| R-01 | [Role catalog as domain config](03-roles/R-01-catalog.md) | F-02 | TODO |
| R-02 | [Composition constraints](03-roles/R-02-constraints.md) | R-01 | TODO |
| R-03 | [Presets by player count](03-roles/R-03-presets.md) | R-02 | TODO |
| R-04 | [Canonical action types](03-roles/R-04-role-actions.md) | R-01 | TODO |
| R-05 | [Bot fairness firewall](03-roles/R-05-bot-fairness.md) | R-04, API-11 | TODO |
| R-06 | [Ruleset versioning (pinned per game)](03-roles/R-06-ruleset-versioning.md) | R-03, G-12 | TODO |

### Phase TG — Telegram client (`04-telegram`)

| ID | Task | Depends | Status |
|---|---|---|---|
| TG-01 | [Bot as API client](04-telegram/TG-01-bot-client.md) | API-15 | TODO |
| TG-02 | [Group adapter](04-telegram/TG-02-group-adapter.md) | TG-01 | TODO |
| TG-03 | [DM adapter & onboarding surfaces](04-telegram/TG-03-dm-adapter.md) | TG-01 | TODO |
| TG-04 | [Keyboard presenter & callback mapping](04-telegram/TG-04-presenter.md) | TG-02, TG-03 | TODO |
| TG-05 | [Mirroring & speech relay](04-telegram/TG-05-mirroring.md) | TG-02 | TODO |
| TG-06 | [Event→message presentation pipeline](04-telegram/TG-06-events.md) | API-09, TG-04 | TODO |
| TG-07 | [Telegram auth bootstrap (initData/session)](04-telegram/TG-07-auth.md) | API-03 | TODO |
| TG-08 | [WebApp client v1](04-telegram/TG-08-webapp.md) | TG-07, API-07, API-08 | TODO |

### Phase C — Generic client (`05-client`)

| ID | Task | Depends | Status |
|---|---|---|---|
| C-01 | [Client state model](05-client/C-01-state.md) | API-07, API-11 | TODO |
| C-02 | [Game card composition](05-client/C-02-game-card.md) | C-01 | TODO |
| C-03 | [Action screens UX](05-client/C-03-actions.md) | API-08, C-01 | TODO |
| C-04 | [Feed rendering](05-client/C-04-feed.md) | API-09 | TODO |
| C-05 | [End screen, vote matrix & share](05-client/C-05-end-game.md) | C-01 | TODO |
| C-06 | [Accessibility & simplified mode](05-client/C-06-accessibility.md) | C-02 | TODO |
| C-07 | [Optimistic UI & reconciliation](05-client/C-07-optimistic.md) | API-06 | TODO |
| C-08 | [Reconnect & offline continuity](05-client/C-08-reconnect.md) | API-07 | TODO |

### Phase M — Mobile (`06-mobile`)

| ID | Task | Depends | Status |
|---|---|---|---|
| M-01 | [Mobile auth & session contract](06-mobile/M-01-contract.md) | API-03 | TODO |
| M-02 | [Swift generated client](06-mobile/M-02-ios.md) | API-15, M-01 | TODO |
| M-03 | [Kotlin generated client](06-mobile/M-03-android.md) | API-15, M-01 | TODO |
| M-04 | [Mobile state sync usage](06-mobile/M-04-sync.md) | API-07 | TODO |
| M-05 | [Push notifications (wakeup-only)](06-mobile/M-05-push.md) | API-09 | TODO |
| M-06 | [Deep links (platform-neutral)](06-mobile/M-06-deep-links.md) | S-03 | TODO |
| M-07 | [Release compatibility gate](06-mobile/M-07-compat.md) | M-01..M-06 | TODO |

### Phase S — Social (`07-social`)

| ID | Task | Depends | Status |
|---|---|---|---|
| S-01 | [Quickplay matchmaking](07-social/S-01-quickplay.md) | G-01, S-02 | TODO |
| S-02 | [Public rooms & discovery](07-social/S-02-public-rooms.md) | G-01 | TODO |
| S-03 | [Sharing & invites](07-social/S-03-sharing.md) | G-01 | TODO |
| S-04 | [Moderation: bans & owner toolkit](07-social/S-04-moderation.md) | G-01 | TODO |
| S-05 | [Reports & karma](07-social/S-05-reports.md) | S-04 | TODO |
| S-06 | [Scheduled community rooms](07-social/S-06-scheduled.md) | S-02 | TODO |

### Phase P — Progression (`08-progression`)

| ID | Task | Depends | Status |
|---|---|---|---|
| P-01 | [Profiles](08-progression/P-01-profiles.md) | G-11 | TODO |
| P-02 | [Statistics & balance telemetry](08-progression/P-02-statistics.md) | P-01 | TODO |
| P-03 | [Rating: facts log & crowns](08-progression/P-03-rating.md) | P-01 | TODO |
| P-04 | [Seasons & leaderboards](08-progression/P-04-seasons.md) | P-03 | TODO |
| P-05 | [Achievements, quests & collection](08-progression/P-05-achievements.md) | P-01 | TODO |

### Phase MEDIA — Assets (`09-media`)

| ID | Task | Depends | Status |
|---|---|---|---|
| MEDIA-01 | [Event art set](09-media/MEDIA-01-event-art.md) | — | TODO |
| MEDIA-02 | [Persona catalog & asset manifest](09-media/MEDIA-02-personas.md) | — | TODO |
| MEDIA-03 | [Client asset URLs](09-media/MEDIA-03-asset-urls.md) | MEDIA-02 | TODO |
| MEDIA-04 | [Telegram file cache](09-media/MEDIA-04-file-cache.md) | MEDIA-01 | TODO |
| MEDIA-05 | [Settings surface for media/avatar](09-media/MEDIA-05-settings.md) | MEDIA-03, P-01 | TODO |

### Phase OPS — Platform & scale (`10-platform`)

| ID | Task | Depends | Status |
|---|---|---|---|
| OPS-01 | [Horizontal scaling & partitioning by gameId](10-platform/OPS-01-scaling.md) | F-01 | TODO |
| OPS-02 | [Locks & atomicity model](10-platform/OPS-02-locks.md) | OPS-01 | TODO |
| OPS-03 | [Async event consumers](10-platform/OPS-03-consumers.md) | API-09 | TODO |
| OPS-04 | [Telegram outbound stays platform-side](10-platform/OPS-04-tg-queues.md) | OPS-03 | TODO |
| OPS-05 | [Observability: ids, metrics, tracing](10-platform/OPS-05-observability.md) | API-01 | TODO |
| OPS-06 | [Load testing](10-platform/OPS-06-load-testing.md) | OPS-01..OPS-05 | TODO |
| OPS-07 | [Soak testing](10-platform/OPS-07-soak.md) | OPS-06 | TODO |
| OPS-08 | [Disaster recovery](10-platform/OPS-08-recovery.md) | OPS-01 | TODO |
| OPS-09 | [Telemetry & privacy](10-platform/OPS-09-telemetry.md) | OPS-05 | TODO |

### Phase L — Delivery & cleanup (`99-delivery`)

| ID | Task | Depends | Status |
|---|---|---|---|
| D-01 | [DB migrations consolidation](99-delivery/D-01-migrations.md) | G-* | TODO |
| D-02 | [Feature flags / kill-switches](99-delivery/D-02-feature-flags.md) | — | TODO |
| D-03 | [Testing strategy](99-delivery/D-03-testing.md) | API-16 | TODO |
| D-04 | [Release runbook & versioning](99-delivery/D-04-release.md) | D-01..D-03 | TODO |
| D-05 | [Cleanup checklist (Phase L)](99-delivery/D-05-cleanup.md) | all above | TODO |

## Definition of Done

### API (gate before TG/C/M phases start implementation)
Scramble generates a valid spec with every endpoint from API-02 · request/response schemas ·
canonical error codes (+messageKey) · auth/scopes documented · two-layer idempotency ·
pagination · revision/projections/capabilities in the envelope · examples validate ·
Scalar UI renders the artifact · committed spec snapshot diff-stable · PHP client builds ·
contract tests pass (API-16) · breaking-change gate green (API-17).

### Telegram client migrated
zero game-domain logic in bot · generated MafiaApiClient used for everything · errors mapped
from canonical codes · rendering isolated in presenters · platform queues outside game
engine · bot restart never interrupts a game.

### Mobile-ready API
no Telegram-specific required fields/file_ids/callback semantics/message-ID-as-game-ID ·
Telegram-independent auth · Swift/Kotlin clients generatable · sync/push/reconnect
documented · version compatibility guaranteed.

## KPIs (single copy — from legacy playability/todo §12)

| Metric | Target |
|---|---|
| /start → first game sitting (new user) | < 60 s |
| Quickplay fill time (empty pool) | ≤ 30 s to bot-fill |
| Games abandoned mid-phase | < 5% |
| AFK freeze incidence after week 1 | declining trend |
| Ghost-chat engagement (dead sending ≥1 msg) | > 40% |
| Median session length | 25–45 min (2–3 games) |
| Concurrency floor | 5+ simultaneous games |
| Average game length | 15–20 min; <1% action error rate |
| p95 action latency under load | < 500 ms |

## Rejected (do not build — single copy)

Energy walls · paid roles/paywalled presets · forced subscription gates · ads in feeds ·
loot-box cosmetics in game surfaces · auto-ping everyone on phase change · mid-game chaos
role-switching (far-future event preset at most) · 2v2 collab board · auto-kick silent humans ·
real-money stakes · swipe/tab-heavy layouts · voice rooms · WebApp-first client gating flows.
Monetization stance: cosmetics only, outside game surfaces, never gameplay-affecting.

## Working rules

1. One task = one focused step (~50–200 spec lines). Execute one task per session:
   read this index + the task file + its listed sources; nothing else.
2. On completion: fill acceptance checkboxes, flip Status to DONE here and in the file,
   never append unrelated work, never create appendices or rev sections.
3. New functionality order: domain concept → API operation → DTO/annotations (Scramble
   regenerates the contract) → contract test → client behavior → Telegram/WebApp/mobile
   presentation. Never backwards. No client is privileged: a Telegram-only gameplay
   capability is a bug.
4. Legacy docs are frozen sources for migration only; cite them in task `Sources:` blocks.
5. All docs in English; data assets (`roles.json`, `lang`, `personas`) stay untouched.

## Data assets living in this folder (not specs)

- `roles.json` — canonical role/ruleset data (source for `03-roles/*`).
- `lang/<locale>/ui.json`, `bot_players.json`, `manifest.json` — phrase packs ru/en/zh/es.
- `personas/<setting>/`, `personas/build.php`, `index.json`, `gallery.html` — persona deck
  prompts/tooling (see `09-media/MEDIA-02-personas.md`).
