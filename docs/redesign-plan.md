# Mafia Game Module — API-First Redesign Plan

> Status: Draft. Created 2026-09-11.
> Scope: Replace MVP in-memory skeleton with production-grade persistence, live Telegram UI, and complete feature set.

---

## Current State

The module has a **solid pure core** (GameSnapshot, NightResolver, VoteTally, WinConditionChecker, RoleCatalog) that is fully tested and I/O-free. The gap is in the **edges**: persistence, live Telegram UI, and missing gameplay features.

### What Works
- 16-role catalog with presets for 5–15 players
- Night resolution (escort → doctor → bodyguard → mafia bloc → solo killers → info roles → elder shield)
- Vote tally with majority, tie → revote, second tie → no-elim
- Win conditions (satanist sacrifice → mafia parity → all-killers-dead → solo last-standing)
- Room lifecycle (create/join/leave/kick/start/finish)
- Bot AI (HeuristicBrain with fairness firewall)
- I18n (4 locales: ru/en/es/zh, ~415 UI keys)
- Discipline (freeze policy: 2 skips → 15min)
- Settings (classic/blitz/tournament templates, feature flags)
- Cron sweep for deadline enforcement

### What's Missing
1. ~~**Persistence** — all state in-memory (lost on restart)~~ ✅ Phase 1
2. ~~**Live cards** — new messages each update instead of `editMessageText`~~ ✅ Phase 2
3. ~~**Callback toasts** — `answerCallbackQuery` not sent~~ ✅ Phase 2
4. ~~**Ghost chat** — dead players can't spectate~~ ✅ Phase 3.1
5. **Quickplay** — random matchmaking (Phase 3.2 pending)
6. ~~**Day actions** — sniper/bandit shot UI (logic exists, no callback route)~~ ✅ Phase 3.3
7. ~~**Secret ballot** — flag exists, always open ballot~~ ✅ Phase 3.4
8. ~~**Bot chatter** — PersonaSpeaker instantiated but not wired~~ ✅ Phase 3.5
9. **Rematch** — callback route exists but incomplete (Phase 4+ pending)

---

## Design Principles

1. **Core stays pure** — no I/O in GameSnapshot/NightResolver/VoteTally/WinConditionChecker
2. **Redis for hot state** — active game snapshots, deadlines, locks
3. **Postgres for durable state** — rooms, members, profiles, game history
4. **One snapshot per game** — serialized JSON in Redis, loaded once per action, written back atomically
5. **Idempotent actions** — every callback is a no-op if already processed (revision check)
6. **Live cards** — `editMessageText` for group phase updates, new messages only for DMs and final state

---

## Phase 1: Persistent Stores (Redis + Eloquent) ✅ DONE

**Goal:** Replace all `InMemory*Store` with production-grade implementations.

### 1.1 Redis Snapshot Store

Replace `InMemoryMafiaStateStore` with Redis-backed implementation.

**Key design:**
- Key pattern: `mafia:snapshot:{gameId}`
- Value: JSON-serialized `GameSnapshot` (versioned, ~2KB typical)
- TTL: 4 hours (auto-cleanup for abandoned games)
- Atomic read-modify-write via `WATCH`/`MULTI` or Lua script
- Secondary indices stored as Redis sets:
  - `mafia:byUser:{userId}` → set of gameId (for "one active game" check)
  - `mafia:byChat:{chatId}` → set of gameId (for chat-scoped queries)

**Operations:**
```php
interface MafiaStateStoreContract
{
    public function load(string $gameId): ?GameSnapshot;
    public function save(GameSnapshot $snapshot): void;
    public function saveWithLock(string $gameId, callable $modifier): GameSnapshot;
    public function delete(string $gameId): void;
    public function gamesForUser(string $userId): array;
    public function gamesForChat(string $chatId): array;
}
```

**Lock strategy:** `saveWithLock` uses Redis `WATCH` on the snapshot key. If the key changed between read and write, retry (max 3 attempts). This prevents lost updates from concurrent callbacks.

### 1.2 Eloquent Room Repository

Replace `InMemoryRoomRepository` with Eloquent models.

**Tables (already migrated):**
- `mafia_rooms` — kind, visibility, status, host, chatId, botId, timers, min/max, role_config, locale
- `mafia_room_members` — room_id, user_id, name, is_bot, state

**Repository implementation:**
```php
interface RoomRepositoryContract
{
    public function find(string $roomId): ?Room;
    public function create(Room $room): Room;
    public function update(Room $room): void;
    public function members(string $roomId): array;
    public function addMember(string $roomId, Member $member): void;
    public function removeMember(string $roomId, string $userId): void;
    public function activeRoomForUser(string $userId, string $botId): ?Room;
    public function lobbyRooms(string $botId): array;
}
```

### 1.3 Eloquent Profile Store

Replace `InMemoryProfileStore` with Eloquent model.

**Table (already migrated):**
- `mafia_profiles` — bot_id, user_id, consecutive_skips, frozen_until, sleepy_total, games_played, wins, favorite_role

**Repository:**
```php
interface ProfileStoreContract
{
    public function get(string $botId, string $userId): array;
    public function incrementSkip(string $botId, string $userId): int;
    public function resetSkip(string $botId, string $userId): void;
    public function freeze(string $botId, string $userId, \DateTimeImmutable $until): void;
    public function recordGame(string $botId, string $userId, bool $won, ?string $role): void;
}
```

### 1.4 Notes Store (Redis)

Replace `InMemoryMafiaNotesStore` with Redis implementation.

**Key pattern:** `mafia:notes:{gameId}:{userId}` → Hash of seat → mark kind
**TTL:** Same as game snapshot (4 hours)

---

## Phase 2: Live Telegram UI ✅ DONE

**Goal:** Replace "new message each update" with `editMessageText` for group phase transitions.

### 2.1 Message Tracker

Track sent message IDs per game phase to enable editing.

```php
interface MessageTrackerContract
{
    public function track(string $gameId, string $phase, int $chatId, int $messageId): void;
    public function lastMessage(string $gameId, string $phase, int $chatId): ?int;
    public function clear(string $gameId): void;
}
```

**Storage:** Redis hash `mafia:messages:{gameId}` → `{phase:chatId}` → `messageId`

### 2.2 Updated Presenters

Modify `GroupPresenter` to:
1. Check `MessageTracker` for existing message in current phase
2. If exists → `editMessageText` (update in place)
3. If not exists → send new message, register with tracker
4. Phase transitions (Night → Day → Vote) reuse the same message

### 2.3 Callback Toasts

Every `CallbackRouterProcessor` action must call `answerCallbackQuery`:
- Success: silent toast (empty text)
- Error: descriptive toast ("Already voted", "Not your turn", etc.)
- Pending action: loading indicator → result toast

---

## Phase 3: Missing Gameplay Features ✅ DONE

### 3.1 Ghost Chat ✅

Dead players can see a private feed of group messages.

**Implementation:**
- `InterfacePresenter::ghostPhaseAnnounce()` sends game card to dead human seats via DM
- `InterfacePresenter::deadHumanSeats()` filters dead human players
- `mirrorGroupMessage()` already sends to all human seats (alive + dead) — group messages are mirrored to dead players
- Ghost messages are read-only (no voting, no night actions) — `relaySay()` blocks dead players

### 3.2 Quickplay Matchmaking

Random matchmaking for users without a room.

**Flow:**
1. User sends `/quickplay`
2. Bot adds them to a matchmaking queue (Redis sorted set by wait time)
3. When 5+ players are queued, create a room, deal roles, start
4. Timeout: if <5 after 60s, notify and remove from queue

**Storage:** Redis sorted set `mafia:quickplay:{botId}` → score: timestamp, value: userId

### 3.3 Day Actions UI ✅

Sniper/bandit shot selection during Day phase.

**Implementation:**
- `GameCoordinator::dayShot()` — eliminates target, consumes bullet, checks win conditions
- `CallbackRouterProcessor` handles `dayshot` and `dayshotcancel` callbacks
- `InterfacePresenter::dayShotMenu()` — target selection keyboard for sniper/bandit
- `InterfacePresenter::dayShotEligibleSeats()` — filters eligible seats (alive, has bullets)
- Language keys: `shoot_prompt`, `shot_announce`, `shot_toast`, `no_bullets_toast`

### 3.4 Secret Ballot ✅

When enabled, votes are anonymous (tally shows totals but not who voted whom).

**Implementation:**
- `GroupPresenter` accepts `ballotMode` (open/secret) from `MafiaSettings`
- Open ballot: `liveTally` shows voter-to-target mapping (`← Alice, Bob`)
- Secret ballot: `liveTally` shows only totals + `secret_note` reminder
- `GameCoordinator::groupPresenter()` passes `settings->ballotMode`

### 3.5 Bot Chatter ✅

Wire `PersonaSpeaker` into `GameCoordinator` so bots occasionally "speak" during DayDiscussion.

**Implementation:**
- `GameCoordinator::botChatter()` generates 2-3 bot messages per day discussion
- `pickChatterCategory()` selects speech category based on game context (greetings/accusations/neutrals/agree/disagree)
- Uses `PersonaSpeaker` with seeded RNG for test reproducibility
- Bots never reveal private information (fairness firewall)

---

## Phase 4: Production Hardening ✅ DONE

### 4.1 Dead Letter Queue ✅

Failed callback processing goes to a DLQ for retry.

**Implementation:**
- `MafiaDlqContract` with push/pop/ack/pendingCount
- `InMemoryMafiaDlq` (tests) + `RedisMafiaDlq` (production)
- `CallbackRouterProcessor` wraps processing in try/catch
- On failure: log + push to Redis list `mafia:dlq:{botId}`
- `mafia:sweep --dlq` processes DLQ items

### 4.2 Metrics ✅

Track game completion rates, average duration, role win rates.

**Implementation:**
- `MafiaMetricsContract` with recordGameCompleted, increment, stats
- `InMemoryMafiaMetrics` (tests) + `RedisMafiaMetrics` (production)
- `GameCoordinator::doEndGame()` records winner, duration, role counts
- Stats: total_games, mafia/town/solo wins, avg_duration_seconds

### 4.3 Graceful Shutdown ✅

On container stop, finish in-progress games before exit.

**Implementation:**
- `MafiaShutdownHandler` with SIGTERM/SIGINT registration
- `GameCoordinator` checks `isStopping()` before advancing phases
- Existing games complete naturally (max 15min for longest phase)

---

## Phase 5: Web App (Mini App)

### 5.1 Game Board

React component showing live game state (seats, phases, roles).

### 5.2 Night Action UI

Private night action panel (select target from seat grid).

### 5.3 Vote UI

Anonymous vote submission with confirmation.

### 5.4 Spectator Mode

Watch a running game (read-only, delayed by 30s to prevent cheating).

---

## Implementation Order

| Phase | Tasks | Estimated Effort |
|-------|-------|-----------------|
| **1.1** Redis Snapshot Store | 5 tasks | Medium |
| **1.2** Eloquent Room Repository | 4 tasks | Low |
| **1.3** Eloquent Profile Store | 3 tasks | Low |
| **1.4** Notes Store (Redis) | 2 tasks | Low |
| **2.1** Message Tracker | 3 tasks | Medium |
| **2.2** Updated Presenters | 4 tasks | Medium |
| **2.3** Callback Toasts | 2 tasks | Low |
| **3.1** Ghost Chat | 3 tasks | Medium |
| **3.2** Quickplay | 4 tasks | Medium |
| **3.3** Day Actions UI | 3 tasks | Medium |
| **3.4** Secret Ballot | 2 tasks | Low |
| **3.5** Bot Chatter | 2 tasks | Low |
| **4.1** DLQ | 2 tasks | ✅ Done |
| **4.2** Metrics | 2 tasks | ✅ Done |
| **4.3** Graceful Shutdown | 1 task | ✅ Done |
| **5.1–5.4** Web App | 8 tasks | High |
| **Total** | **50 tasks** | |

---

## Migration Strategy

1. **No breaking changes** — existing in-memory stores remain as fallback
2. **Feature flag** — `config('mafia.redis_state')` toggles Redis vs in-memory
3. **渐进式 migration** — implement one store at a time, test in staging
4. **Data migration** — no existing data to migrate (MVP is in-memory only)

---

## Dependencies

- **Redis** — required for snapshot store, message tracker, notes, quickplay queue
- **Postgres** — already has migrations for rooms, members, profiles, games
- **php-async-kernel-client** — not needed (synchronous HTTP callbacks)
- **TgSenderContract** — already wired for message delivery

---

## Risks

| Risk | Mitigation |
|------|-----------|
| Redis data loss on restart | Snapshot TTL is 4h; games longer than 4h are rare (max 15min/phase × 6 phases = 90min) |
| Concurrent callback race conditions | WATCH/MULTI with retry (max 3) |
| Telegram API rate limits on editMessageText | Respect 30 msg/sec limit; batch edits |
| Bot chatter reveals info | PersonaSpeaker only sees PublicStateView (same as HeuristicBrain) |
