# Mafia Game — SDD

> **Module:** `tgbot-game-mafia` (`BAGArt\TelegramBotMafia`)
> **Status:** 95% complete (115 tests)

---

## What Was Done

Telegram Mafia game module with API-first redesign. The former plan records persistence, live Telegram cards, ghost viewing, day actions, secret ballots, bot chatter, DLQ and metrics as shipped. Quickplay, rematch acceptance and Mini App acceptance remain in [redesign-plan.md](redesign-plan.md); phase-level completion labels do not close those requirements.

### Core Components

- **Game Core**: Pure domain logic (no I/O). Roles, phases, voting, night actions, win conditions.
- **Redis State**: Hot state (game state, player lists, timers) in Redis snapshots.
- **Eloquent Repositories**: Durable rooms and profiles in PostgreSQL; private game notes use a Redis store.
- **Message Tracker**: `editMessageText` for live Telegram UI updates.
- **Dead Letter Queue**: Failed game events captured for retry/diagnosis.
- **Metrics**: Game metrics (player count, game duration, role distribution).
- **Shutdown**: The former plan describes a signal-aware handler and coordinator stop checks; it does not establish ASK lifecycle integration.
- **Mini App**: Game board, night action UI, vote UI, spectator mode.

### Key Decisions

- Core stays pure (no I/O) — Redis for hot state, Postgres for durable state.
- Idempotent actions (repeated callbacks don't break game state).
- API-first design (all game logic accessible via API, not just Telegram callbacks).

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
- `docs/redesign-plan.md` — Remaining quickplay, rematch and Mini App acceptance work
