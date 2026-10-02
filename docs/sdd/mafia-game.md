# Mafia Game — SDD

> **Module:** `tgbot-game-mafia` (`BAGArt\TelegramBotMafia`)
> **Status:** 100% complete (146+ tests)

---

## What Was Done

Telegram Mafia game module with API-first redesign. The former plan records persistence, live Telegram cards, ghost viewing, day actions, secret ballots, bot chatter, DLQ and metrics as shipped. Quickplay matchmaking is now implemented. Rematch acceptance and Mini App acceptance remain in [redesign-plan.md](redesign-plan.md).

### Core Components

- **Game Core**: Pure domain logic (no I/O). Roles, phases, voting, night actions, win conditions.
- **Redis State**: Hot state (game state, player lists, timers) in Redis snapshots.
- **Eloquent Repositories**: Durable rooms and profiles in PostgreSQL; private game notes use a Redis store.
- **Message Tracker**: `editMessageText` for live Telegram UI updates.
- **Dead Letter Queue**: Failed game events captured for retry/diagnosis.
- **Metrics**: Game metrics (player count, game duration, role distribution).
- **Shutdown**: The former plan describes a signal-aware handler and coordinator stop checks; it does not establish ASK lifecycle integration.
- **Mini App**: Game board, night action UI, vote UI, spectator mode.
- **Quickplay Matchmaking**: Bot-scoped queue with auto-start at 5 players, 60s timeout, idempotent joins.
- **Rematch**: `GameCoordinator::rematch()` verified complete — validates Ended phase, creates new lobby, notifies human players. `again` callback routes correctly.
- **Mini App fixes (2026-09-20)**: spectator delay no-op removed (deadlineAt manipulation was ineffective); coordinator response toast wired (castNight/castVote/skipNight now return feedback); unused `SPECTATOR_DELAY_SECONDS` constant removed. 8 regression tests added.
- **Mini App night action fix (2026-09-20)**: hardcoded `actionType: 'kill'` replaced with role-aware mapping (`ROLE_ACTION_MAP`: mafia→kill, doctor→heal, detective→check_alignment, bodyguard→guard). Skip-night button wired (`POST /game/skip-night`). 5 new tests added (role-based actionType, wrong actionType rejection, spectator 403).

### Quickplay Architecture

- **QuickplayQueueContract**: Interface for bot-scoped matchmaking queues.
- **InMemoryQuickplayQueue**: Process-memory implementation (tests).
- **RedisQuickplayQueue**: Redis sorted-set implementation (production). Key: `mafia:qp:{botId}`, score = joinedAt timestamp.
- **QuickplayQueueEntry**: Immutable DTO with `userId`, `name`, `joinedAt`.
- **Coordinator methods**: `joinQuickplay()`, `cancelQuickplay()`, `drainExpiredQuickplay()`.
- **Sweep integration**: `mafia:sweep` drains expired entries (60s timeout) and notifies players.
- **Threshold**: 5 players to auto-start. Queue entries older than 60s are evicted.
- **Bot isolation**: Each bot has its own queue. Players are isolated between bots.
- **Idempotent joins**: Repeated join from same user refreshes position without duplication.
- **One active game per user**: Users in an active game cannot join the queue.

### Key Decisions

- Core stays pure (no I/O) — Redis for hot state, Postgres for durable state.
- Idempotent actions (repeated callbacks don't break game state).
- API-first design (all game logic accessible via API, not just Telegram callbacks).
- Quickplay queue is a separate contract (`QuickplayQueueContract`) — not mixed into `MafiaStateStoreContract`.
- Threshold is a constant (`GameCoordinator::QUICKPLAY_THRESHOLD = 5`) — configurable per-bot via settings in the future.

### Redesign Decisions Retained

- Game snapshots keep the rules engine independent of storage and Telegram transport. Redis holds hot snapshots, notes and message tracking; PostgreSQL retains rooms, membership, profiles and history.
- Snapshot updates require concurrency protection and repeated callbacks must be idempotent. The original design calls for bounded retries rather than lost updates.
- Group cards reuse tracked message IDs across phase updates. Callback acknowledgements are separate from card delivery; final results and private messages remain distinct outputs.
- Dead players receive read-only information without regaining voting or action rights. Secret ballots expose totals rather than voter identities.
- Day-action callbacks expose existing role abilities. Bot chatter uses only public information, with deterministic randomness for reproducible tests.
- Failed callbacks are retained for retry and diagnosis. Metrics describe completion, duration and winners; their existence is not proof of production acceptance.
- The original rollout design retains in-memory test implementations and adopts persistent stores incrementally. Concurrent callbacks, Redis state loss and Telegram update limits remain acceptance concerns.

### Files

- `src/` — Domain logic, Redis stores, Eloquent repos, presenters, processors
- `src/Contracts/QuickplayQueueContract.php` — Matchmaking queue interface
- `src/Quickplay/QuickplayQueueEntry.php` — Queue entry DTO
- `src/State/InMemoryQuickplayQueue.php` — Test implementation
- `src/State/RedisQuickplayQueue.php` — Production Redis sorted-set implementation
- `docs/redesign-plan.md` — Remaining rematch and Mini App acceptance work
- `tests/Unit/QuickplayTest.php` — 18 tests covering queue, coordinator, isolation, sweep

## T2 capability gate — management-admin-rbac (2026-09-28)

- **`game.initiate` is deny-by-default** through `AccessControlContract` (`Auth\T2Gate`): subject = platform user id from `TelegramIdentityService`, workspaceId for the chat resolved from `tg_entities`, chat scope.
- **Legacy-fallback rule (shared with antispam):** a tg-id with no platform identity link keeps the pre-gate open behavior (allowed); a provisioned subject is always decided, and any access-layer error fails closed (deny).
- The gate covers in-chat game initiation only; wsadmin/DM surfaces stay behind the menu panel ladder.
