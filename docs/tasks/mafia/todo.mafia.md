# Telegram Mafia Bot — Development Plan

> Revision 10. Platform-reality pass over the live platform code: package name/dir fixed
> (`bagart/telegram-bot-mafia-module`, repo `telegram-game-mafia`), boot registration matches
> the actual convention (provider in `bootstrap/providers.php`, self-registration into
> `config('telegram.modules_providers')`), scheduling follows the host pattern
> (`routes/console.php` schedules module Artisan commands — `mafia:sweep` like
> `summarizer:digests` / `tts:prune`; there is no registrar-level cron registration and none
> is needed), seed lang packs + `roles.json` already live in the package `resources/`.
>
> Revision 9. Cross-mode flow clarified: group join = explicit `/play` command only (no join
> button in groups), interface join = button; a group room may go public **only if its Telegram
> group is public**, then interface players join it with ordinary room mechanics and speak via
> the host-bot relay. Nothing dropped.
>
> Revision 8. Release-1 polish pass: onboarding & packaging (**ONB**), safety & moderation
> (**MOD**), quality gates & operations (**OPS**), WebApp feel (**WEB-6..8**) added — M2 ships
> as a finished product, not a feature list. Nothing dropped.
>
> Revision 7 restructured for implementation: **one feature registry with stable IDs**,
> implementation **waves** replace the former parallel "Phases" + "Steps" lists,
> rationale documents archived. Nothing was dropped — every item from rev 2–6,
> `competitive-analysis.md`, `ui-patterns.md` and `playability.md` is absorbed into the
> registry below exactly once.

## 0. Doc Map — single source of truth per concern

| File | Role |
|---|---|
| `todo.mafia.md` (this file) | Master plan: architecture, feature registry (IDs), waves, data model, acceptance |
| `roles.json` | Role mechanics source of truth → RoleRegistry classes, count presets, validation constraints |
| `interface-ux.md` | UI/UX spec: screens, mockups, flows, string-key names (`extras.*`, `extras2.*`) |
| `lang/<locale>/` | Phrase packs ru/en/zh/es (`ui.json`, `bot_players.json`) + `manifest.json` conventions |
| `mafia_personas`: `mafia_persons.md` + `personas/<setting>/` | Persona portrait cards: art direction, prompt decks, build tooling |
| `competitive-analysis.md`, `ui-patterns.md`, `playability.md` | **Archived rationale** — absorbed into this registry; do not use as live spec |

String-key namespaces (lang packs): root keys = core flows · `extras.*` = rev-4 features ·
`extras2.*` = rev-5 UX patterns · `extras3.*` = rev-6 playability items ·
`extras4.*` = rev-8 release-polish surfaces (onboarding, safety, ops).

Status tracking: strike through a feature ID (`~~PLAT-1~~`) once its Done-when passes;
a wave is complete when every ID in its scope is struck.

## 1. Product

Mafia (Werewolf) as an installable platform module. One engine, two skins:

1. **Group mode** — classic party game: `/play` in a group, day talk native, night actions in
   DM/interface, group messages mirrored into each player's private interface.
2. **Interface mode ("rooms")** — games created in the bot DM with random people; each player
   gets a personal UI (game card + feed + buttons); no group needed.

Joining is surface-specific: group members opt in **explicitly with the `/play` command**
(no join button in groups); interface players join with a button (room card / list / deep
link / quickplay). Modes mix: a group game whose Telegram group itself is public can flip its
room to **public** (§3) — it then appears in everyone's room list and anyone joins it from the
main interface with the ordinary interface-room mechanics; such remote seats have no direct
group voice — whatever they say is posted into the group by the host bot («ведущий») with
attribution. Every seat can be filled by an **AI filler bot**: unique nicknames, speech from
phrase packs, strict information firewall.

**Delivery model:** own git repository at `../../..` (working dir
`misc/BAGArt/telegram-game-mafia`), composer name `bagart/telegram-bot-mafia-module`,
PSR-4 `BAGArt\TelegramBotMafia\`, type `library`, own Pest suite. Dev mode (default): the
host `composer.json` maps the namespace PSR-4 into `src/` and keeps a path repository entry —
no composer require needed; edits are immediately visible. Prod mode (servers):
`composer.prod.json` requires versioned `bagart/telegram-bot-mafia-module` from VCS.

Boot registration follows the platform convention: the module's Laravel provider is listed
explicitly in host `bootstrap/providers.php`; on boot it self-registers its module class
into `config('telegram.modules_providers')`:

```php
// bootstrap/providers.php
BAGArt\TelegramBotMafia\MafiaServiceProvider::class,

// MafiaServiceProvider::register()
$providers = (array) Config::get('telegram.modules_providers', []);
Config::set('telegram.modules_providers', array_values(array_unique(array_merge(
    $providers, [MafiaModule::class],
))));
```

No HTTP routes on the chat surface — processing extends through registries only. Exception
for the Mini App track (§5.6): the module ships its HTTP controllers, but route registration
stays with the host app under `/tg/webapp/mafia` — same convention as tg webhook routes being
loaded from the main app, never by the library itself.

Module entry contract:

```php
final class MafiaModule implements TgModuleContract
{
    public static function descriptor(): TgModuleDescriptor
    {
        return new TgModuleDescriptor(
            id: 'mafia',
            name: 'Mafia',
            version: '0.3.0',
            capabilities: [TgModuleCapability::Processor, TgModuleCapability::Command],
            defaultEnabled: false, // opt-in per bot/chat
            failClosed: true,      // enablement-storage error => disabled
        );
    }

    public static function register(TgModuleRegistrar $registrar): void
    {
        $registrar
            ->command('play', PlayCommandProcessor::class)
            ->command('kick', KickCommandProcessor::class)
            ->command('mafia_status', StatusProcessor::class)
            ->command('mafia_end', EndGameProcessor::class)
            ->processor(MessageTypeDTO::class, MafiaMessageProcessor::class)
            ->processor(CallbackQueryTypeDTO::class, CallbackRouterProcessor::class);
    }
}
```

Slash commands route through `TgCommandRegistry` (exclusive interception); everything else
flows through `MessageTypeDTO` / `CallbackQueryTypeDTO` processors. All module-bound
processors declare `moduleId(): 'mafia'`. Dependencies arrive via `BotProcessorContext` in
`build()`; constructors stay lazy. Module boot is fault-isolated by `ModuleBootloader`.

## 2. Fixed Architecture — one engine, three presenters

```
                    ┌──────────────────────────────┐
                    │        GameCore (pure)       │
                    │  state machine · roles ·     │
                    │  resolution · win checks     │
                    └───────────┬──────────────────┘
                                │ GameEvent stream + view reads
      ┌────────────────────┬────┴─────────────┬─────────────────────┐
      ▼                    ▼                  ▼                     ▼
┌──────────────────┐ ┌──────────────────┐ ┌─────────────┐ ┌────────────────────┐
│  GroupPresenter  │ │InterfacePresenter│ │MirrorService│ │  WebAppPresenter   │
│                  │ │                  │ │             │ │ HTML Mini App (WEB)│
└──────────────────┘ └──────────────────┘ └─────────────┘ └────────────────────┘
```

- `GameEventContract`: phase changed, role dealt, action cast, vote cast, elimination, win…
  Presenters subscribe; the core knows nothing about skins. A game always knows its
  `room_id`; presenters read the room's `kind` (group / interface / mixed).
- **MirrorService**: while a game runs, every non-command group message is copied into each
  participant's interface feed. Starts at game start, stops at end/cancel — the mirror flag
  lives inside the snapshot, so restarts resume correctly. Fan-out goes through
  `TgSenderContract` (outbound pipeline owns rate limiting).
- **Speaking relay**: participants without group membership have no direct group voice — they
  speak via `🗣 Сказать` button → forced reply → relayed to all feeds and posted to the group
  by the host bot («ведущий») with attribution (mixed rooms are always publicized group
  rooms, §3).
- **WebAppPresenter (WEB, §5.6)**: Telegram Mini App (HTML) as a *progressive enhancement* —
  reads the same `PublicStateView`/`PrivateView` over an authenticated HTTP API, subscribes to
  the same GameEventContract. All writes enter the **one action pipeline shared with
  callbacks** (RUN-2) — never a parallel business path. Keyboards remain the canonical
  fallback (design rule 12).
- **Private overlay store**: player annotations («pencil marks» sudoku-style, notes) live in a
  dedicated per-user store *outside* the game snapshot — a personal scratchpad, not game
  truth. Writes are atomic per user, never touch the public `rev`, and are invisible to every
  other participant (ROOM-13/20, `interface-ux.md` §15).

### Repository structure

```
telegram-game-mafia/                # repo root, composer bagart/telegram-bot-mafia-module
├── src/
│   ├── MafiaModule.php               # TgModuleContract entry point
│   ├── MafiaServiceProvider.php      # Laravel bindings + migrations
│   ├── Contracts/                    # MafiaStateStore, MafiaClock, RoomRepository, Presenter
│   ├── Core/                         # pure engine, no I/O
│   │   ├── GameStateMachine.php · GameSnapshot.php (readonly, fromJsonV1())
│   │   ├── MafiaGameManager.php · WinConditionChecker.php
│   │   ├── Roles/ (RoleRegistry + 16 role classes) · Actions/ · Voting/
│   │   └── Players/ (views: PublicStateView, PrivateView)
│   ├── Rooms/                        # RoomService, Room, VisibilityPolicy, JoinGuard
│   ├── Presentation/                 # GroupPresenter, InterfacePresenter, renderers, MirrorService
│   ├── BotPlayers/                   # BotPlayer, BotBrainContract, HeuristicBrain, PersonaSpeaker
│   ├── Processors/                   # Play/Kick/Status/End, relay capture, CallbackRouter
│   ├── I18n/                         # LangPack, LocaleResolver, Lang/{ru,en,zh,es}/
│   ├── Images/                       # FileIdCache
│   ├── Persistence/                  # EloquentHistoryRepository, Models/
│   ├── State/                        # Redis snapshot store, lock adapter
│   ├── Discipline/                   # SkipTracker, FreezePolicy, SleepyMarker
│   └── Exceptions/
├── database/migrations/
├── resources/images/                 # event PNGs + personas/<setting>/
├── tests/                            # own Pest suite
├── .gitattributes                    # LF-only
├── composer.json
└── README.md
```

Seed assets status: lang packs (`resources/lang/<locale>/…`) and `resources/roles.json`
already live inside the package; persona portrait decks remain in `docs/tasks/mafia/` until
IMG-4 moves them into `resources/personas/`.

## 3. Room System

Room = lobby entity independent of any chat.

| Property | Values | Notes |
|---|---|---|
| kind | `group`, `interface`, `mixed` | `group` turns `mixed` when an interface-only player joins — possible only for publicized rooms |
| visibility | `private` (default), `public` | see matrix below |
| status | `lobby` → `running` → `finished/cancelled` | joining allowed in `lobby` only |
| capacity | `min_players`–`max_players` (5–15) | chosen by creator |
| role_config | checked roles from catalog | mandatory roles locked ON |

Visibility matrix:

| Created in | Default visibility | Public toggle |
|---|---|---|
| Bot DM (interface) | **public** — visible in everyone's room list | n/a |
| Group | **private** — group members only | yes — **only while the source Telegram group is public**; the room then becomes a listed room and outsiders join it via the usual interface mechanics, playing remotely |

Joining rules (server-enforced, explained toasts): lobby only · capacity · not frozen ·
one active game per user per bot. Surface contract: group humans join **only** by explicit
`/play`; interface joins go through buttons (room card / list / deep link / quickplay).

## 4. Roles — see `roles.json`

16 roles, three teams (town / mafia / solo). `roles.json` is the machine-readable source of
truth: night resolution order (`escort → doctor → bodyguard → mafia_kill → maniac/bandit →
detective/journalist/witness`), win-check order (`satanist_sacrificed → mafia_parity →
all_killers_dead → solo_last_standing`), per-role actions/scopes/immunities/detective-reads,
constraints (mafia ≥1, mafia ≤ ⌊N/3⌋, solo killers ≤2, civilians ≥2), and
`presets_by_player_count` for N=5..15. Creator checkboxes filter optional roles; engine places
mandatory first, then checked specials in preset order, fills rest with civilians;
violations → `rooms.roles_invalid_toast` with reason. Display names/intros live in lang packs.

## 5. Feature Registry

Single backlog. Each feature appears exactly once with a stable ID, its **Wave**
(implementation order, §6) and a verifiable **Done-when**. Detailed behavior/mockups live in
the specs referenced from the Doc Map (§0), not duplicated here.

### 5.1 Platform wiring (PLAT)

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| PLAT-1 | Package skeleton: nested git repo, composer path repo + require, PSR-4, Pest suite, CI (pint+pest), LF-only | W0 | `composer require` works from host; `composer test` green in package |
| PLAT-2 | Module entry: descriptor + register() with 4 commands + 2 processors (§1 snippet) | W0 | `tg:modules:list` shows mafia; commands intercept exclusively |
| PLAT-3 | Enablement: tri-state `tg_module_enablements`, failClosed, `moduleId()` filtering | W0 | disabled chat never fires processors; verified via `tg:module:{enable,disable}` + `tg:modules:doctor` |
| PLAT-4 | `MafiaServiceProvider`: core singletons (`MafiaGameManager`, `RoomService`, `MirrorService`, `NightActionResolver`, `VoteCounter`, `MafiaStateStoreContract`→Redis), migration loader, zero `TgBotSetupFactory` injection | W0 | container resolves contracts; migrations run |
| ~~PLAT-5~~ | Settings: `ModuleSettingsContract::settingsFor('mafia')` JSON (timings, language, ballot mode, max bots); room-level overrides chat-level; package ships defaults | W3 | settings read at start; defaults applied when absent |

### 5.2 Pure core (CORE) — no I/O, fully unit-tested

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| CORE-1 | Domain enums + phase state machine (lobby→night→discussion→voting→end) with transition guards | W1 | illegal transitions throw; unit tests |
| CORE-2 | `GameSnapshot` readonly DTO, versioned `fromJsonV1()`: roster/seats, phase+deadline, mirror flag, `pausedAt`, private section (notes/check results), RNG seed, monotonic `rev` counter | W1 | roundtrip serialize/deserialize stable; `rev` increments on every committed change (client-sync contract) |
| CORE-3 | RoleRegistry seeded from `roles.json`; MVP subset first (civilian, detective, doctor, escort, mafia, godfather) | W1 | registry builds all 16; subset playable |
| CORE-4 | Set builder: presets by joined count N, checkbox filtering, mandatory-first placement, constraint validation with reason codes | W1 | property tests over N=5..15 |
| CORE-5 | NightActionResolver: full resolution order; blocks/saves/deaths; edge cases (doctor self-heal 1×, elder first-night immunity, bomzh untargetable + wasted heal, bodyguard dies-instead) | W1 | table-driven tests per interaction pair |
| CORE-6 | WinConditionChecker in engine order; runs after every death event | W1 | each win condition unit-tested incl. satanist sacrifice |
| CORE-7 | Seeded RNG service: deals + bot decisions reproducible; seed stored in snapshot/audit log | W1 | same seed ⇒ identical game replay |
| CORE-8 | Voting logic: open ballot live tally, tie→revote→no elimination, abstain; secret-ballot mode flag (counts hidden until close) | W1 | ballot scenarios tested; secret flag honored |
| CORE-9 | Info-firewall view types: `PublicStateView` + `PrivateView`; consumers (brains, renders) receive views only — enforced by signatures + arch test | W1 | arch test fails when brain touches snapshot |
| CORE-10 | Remaining roles activation: bodyguard, witness, journalist, elder, bomzh, sniper (instant day shot), turncoat, maniac, bandit, satanist (sacrifice ending) + interaction edge cases | W6 | per-role feature scenarios pass |

### 5.3 Runtime & state (RUN)

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| RUN-1 | Redis snapshot store (`MafiaStateStoreContract`) + per-game lock adapter; snapshot = active-game truth, PG = history only | W2 | concurrent submission test passes under lock |
| RUN-2 | Atomicity: vote/action counters atomic (Lua/increment); callbacks dedupe on `(user, game, phase, action)`; stale taps rejected via `gameId+phaseNumber` | W2 | double-tap and stale-tap tests |
| RUN-3 | Lazy advance + `mafia:sweep` module Artisan command; host `routes/console.php` schedules it (same pattern as `summarizer:digests` / `tts:prune`, config-gated). Restart recovery: snapshots restore, overdue deadlines advance, mirrors resume only if running | W2 | killed-process restart resumes game correctly |
| RUN-4 | `MafiaGameManager`: orchestrates state machine from submissions, emits GameEvent stream, deadline scheduling; no-stall guarantee — unacted bot fires at deadline − `act_before_deadline_sec` | W2 | headless simulated game completes with fake clock/sender |
| ~~RUN-5~~ | Ready-skip ✅: phase closes early when every required actor acted/readied; ready marks on cards; must-act roles never bypassed silently | W3 | phase closes on last readiness; timeout path still works |
| ~~RUN-6~~ | Pause/resume ⏸: host (fallback admin) freezes deadlines; `pausedAt` in snapshot; resume shifts `deadlineAt += pausedDuration`; actions rejected while paused; survives restarts | W3 | pause→restart→resume roundtrip test |

### 5.4 Group flow (GRP) — minimal per-player card ships here; full presenter in W5

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| ~~GRP-1~~ | `/play` lobby card: leave/add-bots/kick/start buttons; joining = `/play` command only (explicit opt-in — no join button in groups) | W3 | lobby lifecycle e2e test |
| ~~GRP-2~~ | DM gate: `interface.dm_required` @mention in group + ready-check card in DM; start blocked until every human confirmed | W3 | unconfirmed player blocks start |
| ~~GRP-3~~ | Start guard: capacity ≥ min, all confirmed → roles dealt to DM/interface → mirror ON | W3 | guard refusals toast with reason |
| ~~GRP-4~~ | Night menus in DM/interface: role-specific target pick, confirm step, cast toast, change-choice | W3 | per-MVP-role menu tests |
| ~~GRP-5~~ | Day voting board (group inline keyboard + duplicated to interfaces): live tally bars `███░░`, timer chip, progress chip (Game Card v2 header, coarse 15 s edits) | W3 | board edits throttled ≥3 s; bars ∝ votes/max |
| ~~GRP-6~~ | End screen: reveal-all roles, duration, sleepy line, rematch (same settings, re-invite humans, bots regenerated); discipline counters update here | W3 | rematch recreates room |
| ~~GRP-7~~ | Host tools: `/kick` picker+confirm+kicked-DM; end-early confirm; replace-leaver toggle | W3 | kicked player loses access; leaver replaced |
| ~~GRP-8~~ | Host ⏱ +30 сек extension, once per phase | W3 | second press rejected with toast |
| ~~GRP-9~~ | Emergency assembly 🆘: alive player drops remaining discussion → vote now; 1/player/game, ≤2/game; night uninterruptible | W3 | budget enforcement tests |
| ~~GRP-10~~ | Room templates 📋 «Классика»/«Молния»/«Турнирный»: one-tap timing/config presets, editable after | W3 | template applies then overrides work |
| ~~GRP-11~~ | Optional seat shuffle 🔀 at deal | W3 | off by default; on → seats randomized |
| ~~GRP-12~~ | Keyboard affordance pass: colored button styles (`InlineKeyboardButton.style` primary/success/danger — DTO supported) on confirm/danger actions, `icon_custom_emoji_id` where it aids scanning | W3 | confirm=success, kick/end=danger across all keyboards |

### 5.5 Rooms & interface mode (ROOM) — spec: `interface-ux.md`

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| ROOM-1 | Room entity/persistence (`mafia_rooms`, `mafia_room_members`), status lifecycle, `RoomService`, `JoinGuard` (lobby/capacity/freeze/single-game) with refusal toasts | W5 | join-refusal matrix tested |
| ROOM-2 | Visibility policy: DM-created=public, group-created=private + toggle gated on the source Telegram group being public; list visibility enforced | W5 | matrix §3 verified incl. toggle refused for non-public groups |
| ROOM-3 | Room list UI + room card + `🔄 Обновить` (explicit refresh, no auto-refresh) | W5 | rows render via `rooms.room_row` |
| ROOM-4 | Creation wizard (forced reply, cancellable): title → range → role checkboxes (mandatory locked, in-place toggle) → visibility. Under WebApp availability collapses to ONE form (WEB-4); keyboard chain stays fallback | W5 | invalid sets rejected with reason; both wizard variants produce identical `role_config` |
| ROOM-5 | Deep links `t.me/<bot>?start=mafia_room_<id>` open room card anywhere + `copy_text` invite button in room card | W5 | invite joins lobby; copy button places link into clipboard |
| ROOM-6 | InterfacePresenter: Game Card single live-edited message, feed (plain sends), transient action screens; three-message layout discipline; minimal card upgraded from W3 | W5 | stale-free card under rapid phase churn |
| ROOM-7 | CallbackRouterProcessor: routes all inline keyboards; immediate `answerCallbackQuery` toasts; dead ends forbidden (errors explain next step) | W5 | every keyboard action answered |
| ROOM-8 | MirrorService: non-command group messages → every participant's feed; commands/bot messages/media skipped; batched through outbound queue; starts/stops exactly with game | W5 | mirror flag off ⇒ zero sends |
| ROOM-9 | Speaking relay 🗣 both directions: forced-reply capture → attribution post to group + all feeds (public mixed rooms); per-user speech cooldown (`extras.cooldown_toast`) | W5 | cooldown rejects within window |
| ROOM-10 | Quickplay ⚡: locale-matched matchmake into open public room; empty pool → bot-fill countdown with ETA | W5 | fill ≤30 s to bots (KPI) |
| ROOM-11 | ↩️ Вернуться в игру deep link on profile/main menu while a game runs | W5 | returns into running game |
| ROOM-12 | Ghost chat 👻: eliminated get dead-only feed + dead-to-dead speech (nothing reaches living until reveal); ghost predictions 👻 bet the winner → «прозорливость» stat at reveal | W5 | audience-filter isolation test |
| ROOM-13 | Private annotation layer «pencil marks» (sudoku-style): per-seat stacked icon marks — manual palette 🔪 suspect / 🟢 clear / ❓ doubt / ⭐ my-vote-target + auto ✔️/❗️ from own checks; rendered as compact cluster on YOUR card view only, full stack in seat inspector; wiped at game end unless persisted to profile | W5 | leak scan: marks absent from shared surfaces and from every other user's views |
| ROOM-14 | Training mode 🎓: instant all-bot interface room, roles rotate, no strikes/stats, exit anytime *(moved from P1 — requires InterfacePresenter; coach hints cover newcomers until then)* | W5 | game completes vs bots, zero profile writes |
| ROOM-15 | Opt-in phase pings 🔔 (default OFF, silent DM on phase change) | W5 | OFF ⇒ nothing sent |
| ROOM-16 | Avatar picker 😎 (emoji pool shared with bot personas), prepends name everywhere | W5 | choice persists to profile |
| ROOM-17 | ♿ Simplified formatting toggle: no bars/spoilers/ASCII tables, short plain lines | W5 | toggled render passes accessibility checks |
| ROOM-18 | `/mafia_log`: last ~20 public events (deaths, phases, vote closes; saves hinted) | W5 | rejoiner catches up without leaks |
| ~~ROOM-20~~ | Annotation store & contracts: `MafiaNotesStoreContract` (toggle/set/clear/wipe) + Redis impl keyed `mafia:notes:{roomId}:{userId}` — deliberately **outside the game snapshot**: own `notesRev`, atomic per-user writes, no game lock, no public-`rev` bumps; TTL = game end + grace | W5 | toggling marks never wakes other players' long-polls; firewall arch test covers store scope |
| ROOM-21 | Marks keyboard UX: seat inspector gains a palette row of toggle chips (tap = on/off, sudoku-style stacking); inline card shows ≤3 marks per seat in fixed order (auto first), overflow lives in inspector/WebApp | W5 | toggle roundtrip ≤2 taps from card; inline cap enforced |
| ROOM-19 | Wills ✍️ optional room preset (default off): night victim pre-writes one message shown at morning reveal | M3 | preset off ⇒ zero prompts |

### 5.6 Telegram Mini App (WEB) — HTML presenter, first slice of M3

Progressive enhancement over the existing presenters: keyboards stay canonical, the Mini App
removes their hard limits (no edit throttling, real forms, real chat panels, rich HUD).
DTO support already generated: `web_app` inline buttons (private chats), `MenuButtonWebApp`,
`initData`/`WebAppData`, `answerWebAppQuery`, direct links `t.me/<bot>?startapp=`.
Screen/UX detail lives in `interface-ux.md` §13.

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| WEB-1 | Auth: `initData` HMAC validated against the room's bot token from DB (multi-bot — resolve by `bot_id`), `auth_date` freshness ≤1 h, short-lived signed session issued to the client | W7 | forged/replayed initData rejected; session expiry enforced |
| WEB-2 | Read API: `GET /tg/webapp/mafia/state/{roomId}` → `{PublicStateView, PrivateView, notes, rev, notesRev}`; long-poll (hold ≤25 s) wakes on either `rev` or own `notesRev` bump, plain-poll fallback; view-firewall arch test extended to HTTP layer | W7 | cross-user private-view leak test fails closed; phase change visible <1 s |
| WEB-3 | Action API: submissions routed into the same ingress pipeline as callbacks (RUN-2 dedupe/locks/idempotency) — single write path for all surfaces; exception: pencil-mark toggles (`POST /notes/...`) use the same auth but a light single-writer path without the game lock (ROOM-20) | W7 | double-submit idempotent; stale phase rejected; mark toggle never takes game lock |
| WEB-4 | Screen set v1: game card + roster + HUD dots + marks editor (palette popover per seat), night-action forms (real inputs), live vote grid, notes editor, seat inspector, ghost-chat panel, end screen + vote matrix; room wizard collapses to one form (ROOM-4 fallback kept) | W7 | parity checklist (`interface-ux.md` §13) green |
| WEB-5 | Launch & fallback rules: every entry point pairs a `web_app` button with a callback equivalent; client capability detection; direct-link `?startapp=<roomId>`; sanitize + strict CSP for mirrored group text | W7 | game fully completable with WebApp never opened |
| WEB-6 | Theme & viewport sync: `themeParams` → CSS variables (dark/light auto), safe-area insets, viewport height handling, BackButton wired to the screen stack | W7 | no contrast or clipping glitches in light and dark clients |
| WEB-7 | Feel pass: haptic feedback on cast/vote/mark-toggle, phase-change transitions + skeleton loaders for deferred state, optional sound toggle (default off), closing confirmation while an action is unsent | W7 | interaction checklist green; zero layout shift under phase churn |
| WEB-8 | Resilience: stale-data banner + auto-reconnect with backoff, reconciliation by `rev`/`notesRev` after a gap, «connection lost — use the keyboard» hint linking back to chat | W7 | network killed mid-phase ⇒ client recovers without duplicate actions |

W7 opens with a half-day spike proving WEB-1..WEB-2 end-to-end before any UI work starts.

### 5.7 AI bot players (BOT)

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| BOT-1 | NicknameFactory: per-locale pool (`bot_players.json → names.pool`), collision suffix `_N`, avatar emoji per persona | W2 | no collisions across 1000 draws |
| BOT-2 | HeuristicBrain: suspicion scoring from votes/claims/randomness; seeded decisions; fires before deadline − `act_before_deadline_sec` | W2 | deterministic under fixed seed |
| BOT-3 | PersonaSpeaker: seeded variants (greetings, accusations, defenses, fake claims by mafia bots, last words, win/lose); timing knobs (reply delay 3–25 s, chatter probability, typing indicator); renders `[name]: text` + persona emoji | W2 | speech never references hidden info (firewall test) |
| BOT-4 | Fairness firewall arch test: brains consume views only (with CORE-9); seeded audit logs | W2 | arch test red on violation |
| ~~BOT-5~~ | Capacity rules: fill toward `max_players` or +1 increments; hard cap `errors.bots_limit_reached`; host-only control | W3 | cap enforced |
| ~~BOT-6~~ | Replace-leaver inheritance: fresh bot inherits exactly the seat's knowledge state — nothing more (setting, default on) | W3 | inherited view equals departed human's view |
| BOT-7 | Personality profiles for brains | M3 | distinct behavioral profiles observable |

### 5.8 i18n & onboarding (I18N)

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| ~~I18N-1~~ | LangPack loader: `{placeholder}` interpolation + HTML escaping of user data; CLDR plurals (one/few/many/other); buttons ≤24 chars | W4 | escaping test on hostile input |
| ~~I18N-2~~ | LocaleResolver chain: room → chat → bot default → `en`; `settings.language_button` switcher | W4 | chain order test |
| ~~I18N-3~~ | Wire ru/en across all implemented surfaces; parity test (key-set equality) in CI — missing `zh` key fails build | W4 | parity green |
| I18N-4 | Activate zh/es (files exist; wiring trivial once I18N lands) | W6 | all 4 locales render; parity green |
| ~~I18N-5~~ | First-run tutorial DM (profile flag) + `/rules` inline paginated wiki from lang packs | W4 | fires once ever; rules pages navigate |
| I18N-6 | Coach hints: contextual tips in action screens, first 3 games only, dismissible (`extras3.*`) | W5 | disappears after 3 games |
| I18N-7 | Copy-edit pass over ru/en before M2: consistent tone, fixed terminology (seat/phase/vote terms), buttons re-measured ≤ 24 chars | W6 | copy-review sign-off; button-length lint green |

### 5.9 Discipline, social & meta (DISC)

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| ~~DISC-1~~ | AFK freeze: skip = seated but zero interactions OR left early; strike progress `{skips}/2`; two consecutive → frozen 15 min (cannot join); full game resets; persists per user per bot | W3 | freeze lifecycle test incl. persistence |
| ~~DISC-2~~ | Sleepy badge 😴: missed public day vote while alive → badge on card rest of game, announced once, totaled at end + lifetime stat | W3 | badge lifecycle test |
| DISC-3 | Profiles & stats: `mafia_profiles` (games, wins, favorite_role, sleepy_total, skips, frozen_until); 📊 statistics screen from history tables | W6 | stats match played games |
| DISC-4 | Vote matrix 📊 on end screen: full who-voted-whom grid (dispute killer) | W6 | grid matches recorded ballots |
| DISC-5 | Shareable result card PNG 📤: pre-rendered summary posted with deep-link caption (organic invite loop) | W6 | card contains working deep link |
| DISC-6 | Peer MVP voting + superlative nominations: «лучший ход», «лучшее вскрытие», «Тень года» | W6 | nominations render on end screen |
| DISC-7 | 🔥 Streak badge next to name (public, factual, capped display) | W6 | streak counts consecutive wins |
| DISC-8 | Reports → moderation queue, karma score, hosting restrictions for low karma | M3 | report lands in queue |
| DISC-9 | Achievements & collection: role collection gallery (unlock by playing the role); daily quests (cosmetic-only); login streak feeds quest rerolls *(competitive rating/seasons/leaderboards live in RAT §5.10)* | M3 | quest completion tracked |

### 5.10 Ratings & championships (RAT) — «короны», public ranked play

**Principle — event sourcing:** the database stores only *immutable facts* (who sat with
whom, which team won, who left mid-game). Crowns (👑, integer rating units) are **never
authoritative** — they are a versioned projection computed from the eternal facts log, so
formula changes and dispute investigations replay from scratch. Ranked applies to **public
rooms only**: want rating — play rated lobbies, and high-rank lobbies matter most.

Incentive balance (formula properties, verified by property tests):

- Beating a much weaker lobby yields ≈ 0 crowns for a top-rated player (Elo expectation);
- High-tier lobbies (strong participants) amplify stakes for everyone;
- Leaving mid-game counts as a loss regardless of the final outcome;
- Newcomers pass a provisional period before their crowns count/display.

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| RAT-1 | Immutable facts log: `mafia_games` gains `is_ranked`, `tier`, `season_id`, `avg_crowns_at_start`; `mafia_players` gains `leave_status(none/left/replaced)` + `left_at_phase`; append-only after game end (no UPDATE/DELETE — enforced by arch test) | W8 | mutation attempt fails; every finished ranked game yields complete facts |
| RAT-2 | Ranked eligibility: public visibility + ≥70% human seats + not training mode; otherwise the game is marked unranked | W8 | eligibility matrix tested incl. edge cases |
| RAT-3 | Lobby tier at deal: bucket function over seated crowns; rendered as «🏆 Ранг N» on lobby card / room card / room list | W8 | tier strictly non-decreasing in participant crowns |
| RAT-4 | Crown formula v1 (versioned `formula_version`): `Δ = K · tierFactor · (S − E)` where `E = 1/(1+10^((R_lobbyAvg − R_self)/400))`, `S ∈ {1,0}`, `K` decays with own-crown brackets, `tierFactor = 1 + 0.15·(tier−1)`; leaver forces `S = 0`; floor at 0; defaults tunable via settings | W8 | property tests: weak-lobby win for top player ≤ +2; high-tier swing > low-tier swing; leaver always loses crowns |
| RAT-5 | Projector: idempotent ordered-facts consumer → `mafia_ratings` cache (crowns, ranked_games, `last_fact_seq`) + `mafia:ratings:rebuild {season}` full-replay command | W8 | double rebuild byte-identical; resume from `last_fact_seq` |
| RAT-6 | Seasons & leaderboards: season windows per bot, soft reset `crown' = anchor + (crown − anchor)/2`, weekly + season boards, champion title | W8 | reset math unit-tested; board matches projection |
| RAT-7 | Championships (absorbs the former tournament track): entry gate = min crowns (`rooms.join_low_rating_toast` in JoinGuard), bracket tooling (TO bulk-create rooms, advance winners, export), championship games ranked with tier boost | W8 | below-threshold join rejected; bracket completes end-to-end |
| RAT-8 | Crowns UI across surfaces: 👑 badge next to names (rosters, feeds, share cards, WebApp), tier lines, leaderboard screen, «?» during provisional period (first N ranked games) | W8 | provisional renders as «?»; badge everywhere consistent |

Defaults: anchor = 100 crowns, provisional = 5 ranked games, K₀ = 32. All constants live in
package settings, overridable per bot.

### 5.11 Event images & personas (IMG) — generation workflow: `mafia_persons.md`

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| IMG-1 | Event PNG set (§8 assets: lobby banner, night falls, morning death/quiet, voting, eliminated, 4 win screens, sleepy sticker, 16 role cards) via `sendPhoto` + localized captions; flat style, dark base, team accent, no text baked in | W6 | all assets render in-game |
| IMG-2 | FileIdCache: asset path hash → cached Telegram `file_id` per bot; upload once | W6 | second send uses file_id (no re-upload) |
| IMG-3 | Death-scene art variety: 2–3 variants per event, seeded-random pick | W6 | variant selection deterministic per seed |
| IMG-4 | Persona integration: setting picker in wizard (default random), snapshot stores `setting_id`, portraits as deal-reveal cards, missing file ⇒ emoji fallback (never blocks a game) | W6 | fallback path tested |
| IMG-5 | Native TG reactions on event images (`setMessageReaction` — DTO supported); cosmetic only, never required for flow | W6 | reactions settable; zero flow dependency |

### 5.12 Advanced (ADV) — rest of M3 after W8

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| ADV-1 | Provable fairness: publish `SHA-256(serverSeed)` before dealing, reveal after game | M3 | third party verifies deal |
| ADV-2 | Balance telemetry: win-rate/survival per role per count-bucket dashboard → versioned preset tuning with changelog; rating-anomaly flags (collusion, win-trading) → moderation queue only, never auto-punish | M3 | dashboard populated; anomaly flag lands in queue |
| ADV-3 | Preset share-codes: role-config serialized to short code, import in wizard (`rooms.import_code`) | M3 | export→import roundtrip |
| ADV-4 | Scheduled community rooms: cron-opened public rooms («каждый вечер в 20:00») | M3 | room opens on schedule |
| ADV-5 | Web dashboard over history tables, log export, spectator links (privacy review needed) | M3 | read-only access verified |
| ADV-6 | Keep `mafia:sweep` as the scheduling path — the platform registers cron via host `routes/console.php`, not via `TgModuleRegistrar`; revisit only if a registrar-level cron registration lands upstream | M3 | n/a — closed by rev 10 platform pass |
| ADV-7 | Spectator mode: join a public running room as read-only observer (`PublicStateView` only), spectator count on the card, zero private-layer exposure | M3 | firewall test: observer sees exactly the public view |

### 5.13 Onboarding & packaging (ONB) — the release-1 face of the product

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| ~~ONB-1~~ | `/start` router: welcome card with quick actions (⚡ Quickplay / 📋 Rooms / 🎓 Training / 📖 Rules / 🌍 Language); deep links (`mafia_room_*`, `startapp`) bypass straight to the target surface; a brand-new user is ≤ 2 taps from sitting at a table | W4 | fresh account reaches a training room in ≤ 2 taps; every deep-link variant routes correctly |
| ~~ONB-2~~ | Role encyclopedia `/roles`: all 16 roles rendered from `roles.json` + lang packs (what it does, win condition, night-order position, tips); reachable from rules pages and from the role-deal reveal («?» tap) | W4 | every role page renders in all locales; deal reveal links to its role page |
| ~~ONB-3~~ | Bot profile packaging: `setMyCommands` menu, localized description/about, chat menu button, `/start` attachment image; owner setup runbook (connect → enable module → commands) | W4 | fresh bot install playable in < 5 min following README only |

### 5.14 Safety & moderation (MOD)

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| MOD-1 | One-tap report ⚠️ in seat inspector and profiles: last-N-events snapshot + room/user context → bot-owner chat queue; per-user daily cap against abuse | W6 | report lands with replayable context; cap enforced |
| MOD-2 | Input hygiene: name length/clipping rules, HTML-escape everywhere (§8), optional profanity filter for names/wills/speech (per-bot setting), seat-number anonymizer honored on every surface | W6 | hostile inputs render safely in all locales; filter toggles via settings |
| MOD-3 | Owner toolkit: global ban list (`mafia_bans`: reason, expiry) enforced in JoinGuard at every entrance; active-games overview; force-end / kick from owner chat | W6 | banned user blocked from join/quickplay/deep-link; force-end finishes the game cleanly |
| MOD-4 | Privacy & data control: `/privacy` (what is stored, retention), `/delete_me` purges profile + history rows for that user on that bot; telemetry exclusion flag | W6 | purge leaves zero rows for the user; privacy text matches actual storage |

### 5.15 Quality gates & operations (OPS) — «конфетка» must be verifiable

| ID | Feature | Wave | Done-when |
|---|---|---|---|
| OPS-1 | Product telemetry: anonymous event log (`mafia_telemetry`) — funnel /start → first game, drop-off per phase, §12 KPI queries; dashboard SQL shipped in the package; no message content, ids only, 90-day retention | W5 | every §12 KPI computable from live data; schema review confirms no PII beyond ids |
| OPS-2 | Alerting: DLQ depth, error rate, stalled-phase age, sweep failures → throttled owner-chat alerts; health endpoint extended to cover them | W6 | induced failure fires exactly one throttled alert; recovery clears it |
| OPS-3 | Load & soak suite: 5 concurrent 15-player simulated games (KPI floor), mirror fan-out burst, long-poll storm; soak ≥ 2 h with bounded memory and Redis keys | W6 | p95 action latency < 500 ms; zero lost actions; Redis key count returns to baseline |
| ~~OPS-4~~ | Kill-switches: every risky feature (wills, reactions, pings, ghost predictions, marks, WebApp launch buttons) togglable via package settings without deploy; default-safe values | W4 | flipping a setting changes behavior on next tick; both states tested |
| OPS-5 | Perf budget in CI (nightly): headless 15-player game completes under time budget; edit-throttle and batching assertions included | W6 | budget breach fails the job |
| OPS-6 | Release runbook & versioning: semver + CHANGELOG in the package, migration notes, smoke checklist (enable → play → end → stats), rollback = disable module (failClosed) + migrate back | W6 | runbook dry-run passes on a clean environment |

### 5.16 Rejected (do not build)

Energy walls · paid roles / paywalled presets · forced subscription gates · ads in feeds ·
loot-box cosmetics in game surfaces · auto-ping everyone on phase change · mid-game chaos
role-switching events (revisit as event-weekend preset, far future) · 2v2 collab board ·
auto-kick silent humans (replace-with-bot covers it; kicking feels hostile) · real-money
stakes. Monetization stance: cosmetics only, outside game surfaces, never gameplay-affecting.

## 6. Waves & Milestones

Dependency order; each wave ends with its exit criteria (tests are the gate).

| Wave | Scope (IDs) | Exit criteria |
|---|---|---|
| **W0** (d1) | PLAT-1..4 | module discovered/enabled/disabled via `tg:*` CLI; package CI green |
| **W1** (d2–4) | CORE-1..9 | unit suite: state machine, resolution order, win order, set builder N=5..15, seeded-RNG replay, firewall arch test |
| **W2** (d5–7) | RUN-1..4, BOT-1..4 | headless simulated game completes (fake clock/sender, deterministic bots); concurrency + dedupe tests; restart recovery |
| **W3** (d8–10) | GRP-1..11, RUN-5..6, BOT-5..6, DISC-1..2, PLAT-5 | full group game e2e with bots incl. ready-skip, pause/resume, +30s, emergency, templates; sweep advances stalled phase; freeze/sleepy lifecycle |
| **W4** (d11–12) | I18N-1..3, I18N-5, ONB-1..3, OPS-4 | ru/en complete over implemented surfaces; parity test wired; tutorial + `/rules` fire; `/start` router, `/roles`, bot packaging done; kill-switches flip without deploy |
| **W5** (d13–17) | ROOM-1..18, ROOM-20..21, I18N-6, OPS-1 | mixed public-room game e2e; mirror starts/stops exactly; quickplay fill ≤30 s; callback idempotency; ghost isolation; marks never wake other players; telemetry funnel records first games |
| **W6** (d18–22) | CORE-10, I18N-4, I18N-7, DISC-3..7, IMG-1..5, MOD-1..4, OPS-2..3, OPS-5..6 | 16-role catalog validated; secret-ballot mode togglable via settings (CORE-8 flag); images shipped; full-game feature tests (humans mocked, bots deterministic); load+soak green, alerting live, moderation & privacy commands work; `tg:modules:doctor` clean; README + release runbook |
| **W7** (post-M2, first slice of M3) | WEB-1..8, host route registration | half-day spike proves WEB-1..2 e2e before UI work; parity checklist green; forged-initData + cross-user leak tests pass; phase change visible <1 s via long-poll; theme/haptics/resilience checks green (§16.2); game completable without opening the app |
| **W8** (M3) | RAT-1..8 | double rebuild of crowns byte-identical; formula property tests green (weak-lobby farming ≈ 0, tier amplification, leaver penalty); eligibility gating e2e incl. `join_low_rating_toast`; championship bracket completes |

**Milestones:** **M1** = end of W4 — playable group MVP (ru/en, packaged bot profile).
**M2** = end of W6 — full release under the **release-1 polish gate**: no placeholders on any
surface, all locales complete (I18N-7 sign-off), analytics + alerting live (OPS-1..2),
load-tested (OPS-3), moderation & data-control commands working (MOD-1..4), runbook drilled
(OPS-6). The checklist form lives in `interface-ux.md` §16.
**M3** = advanced backlog — starts with **W7** (Mini App v1 incl. feel/resilience pass),
then **W8** (ranked «короны» + championships), then ADV-*, BOT-7, DISC-8..9, ROOM-19.

## 7. Data Model

PostgreSQL = finished games/history/stats/discipline. Redis snapshots = active-game truth.

```sql
-- Rooms & membership
mafia_rooms: id uuid pk, kind enum(group,interface,mixed), chat_id nullable,
    title, host_user_id, visibility enum(private,public),
    status enum(lobby,running,finished,cancelled),
    min_players int, max_players int, role_config jsonb, locale char(2),
    created_at, started_at, finished_at
mafia_room_members: room_id fk, user_id, is_bot bool, seat int,
    state enum(joined,left,kicked,replaced), unique(room_id, user_id)

-- Finished-game history
mafia_games: <existing shape> + room_id fk, locale
mafia_players / mafia_night_actions / mafia_votes: unchanged shape

-- Discipline & stats per user per bot
mafia_profiles: bot_id, user_id, consecutive_skips int default 0,
    frozen_until timestamptz null, sleepy_total int default 0,
    games_played int, wins int, favorite_role null

-- Ratings & seasons (derived projections; see RAT §5.10)
mafia_seasons: id, bot_id, starts_at, ends_at, anchor_crowns int, status enum(active,closed)
mafia_ratings: bot_id, user_id, season_id, crowns int, ranked_games int,
    last_fact_seq bigint, unique(bot_id, user_id, season_id)

-- Immutable facts additions (append-only after game end)
mafia_games: + is_ranked bool, tier int null, season_id fk null, avg_crowns_at_start int null
mafia_players: + leave_status enum(none,left,replaced) default none, left_at_phase int null

-- Release-1 ops (telemetry aggregate-first, bans)
mafia_telemetry: id bigserial, bot_id, event text, props jsonb, user_id null, created_at;
    no message content, 90-day retention, /delete_me purge honored
mafia_bans: bot_id, user_id, reason text, until timestamptz null, created_by,
    unique(bot_id, user_id)
```

Redis keys: `mafia:snapshot:{roomId}` (GameSnapshot JSON, versioned),
`mafia:lock:{roomId}`, `mafia:votes:{roomId}:{phase}` (atomic counters); mirror flag and
seed live inside the snapshot; `mafia:notes:{roomId}:{userId}` — private pencil-marks
overlay with own `notesRev`, outside the snapshot (ROOM-20).

## 8. Technical Decisions

- **Active-game truth** = readonly `GameSnapshot` in Redis; no behavior/closures cross the
  boundary; callbacks prefer classes. PostgreSQL = history + discipline + stats.
- **Messaging**: everything through `TgSenderContract` — the outbound pipeline owns rate
  limits; live-board edits throttled (≥3 s); mirrors batched by the queue.
- **Timers**: scheduling goes through the host `routes/console.php` pattern — the module
  ships the `mafia:sweep` Artisan command, the host schedules it config-gated (like
  `summarizer:digests` / `tts:prune`); lazy advance covers every interaction path.
- **Ratings are derived, never authoritative**: the append-only facts log (who played whom,
  who won, who left) is the single truth; crowns are a versioned projection (RAT-5) — formula
  changes and disputes replay from the log, never patched in place.
- **Annotations are scratchpad, not state**: pencil marks/notes never enter `GameSnapshot` —
  high-churn personal data would poison snapshot purity and wake unrelated syncs; they ride a
  separate store with its own revision (ROOM-20) and die with the game unless persisted.
- **Privacy**: hidden info renders only in DM/interface; group texts never leak roles/votes;
  HTML-escape all user-provided names (or anonymize via seat numbers setting).
- **Bot fairness**: view-based brains only (CORE-9/BOT-4), seeded decisions auditable.
- **Telemetry is aggregate-first**: events carry ids and counters, never message content or
  private-view data; 90-day retention; `/delete_me` purges (OPS-1, MOD-4). Product decisions
  from §12 KPIs must be answerable without ever reading chats.
- **Every risky feature has a kill-switch**: settings-driven, default-safe, effective on the
  next tick without a deploy (OPS-4) — polish never ships as an un-rollback-able behavior.

## 9. Design Rules (review checklist for every PR)

1. **No single point of waiting** — every blocking phase has an accelerator (ready-skip,
   replace-bot, auto-advance); nothing waits longer than one timeout on one absent human.
2. **Every dispute resolvable from artifacts** — seeded RNG + audit log + vote matrix ⇒ any
   result replays.
3. **Boredom budget ≤ 20 s** — a player always has an action or a show (feed, ghost chat).
4. **Growth loops user-initiated** — share-cards and deep links, never forced gates.
5. **One surface of truth per player** — the live game card; auxiliary messages ephemeral.
6. **Glanceability** — the card answers “who am I, whose turn, how long” without scrolling.
7. **Private layer rich, public layer calm** — HUD dots/inspector/notes never leak to shared
   surfaces; privacy by layout, not by promise.
8. **Recognizable over original** — mirror famous shapes (bars, emergency button, ghost
   busy-work) so skill transfers instantly.
9. **Never color-only signals** — dots paired with text labels; buttons carry seat numbers +
   names (screen-reader safe); cooldowns limit sends, not typing time.
10. **Dead ends forbidden** — every error toast explains what to do next.
11. **Fail closed, boot isolated** — enablement errors disable the module; boot failure never
    breaks the platform.
12. **Keyboard parity** — every WebApp screen has a keyboard equivalent; the game is always
    completable without opening the Mini App; the WebApp never gates a flow.
13. **Graceful degradation everywhere** — missing asset ⇒ emoji fallback (IMG-4), WebApp
    unavailable ⇒ keyboard (rule 12), missing locale ⇒ resolver chain to `en` (I18N-2), dead
    end ⇒ explanatory toast (ROOM-7); nothing hard-fails the player.
14. **Reportable & governable** — every public surface offers a one-tap report path; the
    owner can observe and force-end any live game; a ban blocks every entrance (MOD-1/3).

## 10. Testing Strategy

- **Unit (package)**: pure core, role interactions, resolution order, win conditions,
  set-builder validation, i18n escaping/plurals.
- **Feature (package)**: full simulated games — deterministic bots, fake clock, fake sender;
  pause/resume/restart roundtrips; mirror on/off boundaries.
- **Integration (host)**: enablement toggles, settings, migrations, command interception.
- **Contract tests**: presenter event contract; lang-pack key parity across locales (CI).
- **Arch tests**: fairness firewall (brains touch views only); no `TgBotSetupFactory` leakage.
- **Load/soak (nightly)**: OPS-3 simulation — concurrency floor, mirror fan-out bursts,
  long-poll storms, memory/Redis-key-bounded soak.
- **Telemetry feature tests**: funnel events fire exactly once per transition;
  `/delete_me` removes every row for the user; kill-switch states both covered.
- **Manual**: real-group playtests; mirror fan-out under load; light/dark theme walkthrough.

## 11. Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Race conditions on actions | High | Per-game lock, atomic counters, idempotent callbacks (RUN-1..2) |
| Mirror fan-out spam / rate limits | High | Pipeline batching, edit-throttling, cooldowns (ROOM-8..9) |
| Phase stalls in inactive chat | Medium | Lazy advance + `mafia:sweep` (RUN-3) |
| Bot leaks info (“бот читит”) | Critical | View-based brains + arch test + seeded logs (CORE-9, BOT-4, ADV-1) |
| Players without DM break night phase | High | Ready-check gate blocks start (GRP-2) |
| AFK participants ruin lobbies | Medium | Freeze after 2 skips, replace-with-bot (DISC-1, BOT-6) |
| Info leaks in group chat | High | Privacy-by-layout rules (rule 7), delayed/spoiler reveals |
| Module boot failure breaks platform | Low | ModuleBootloader isolation, failClosed descriptor |
| Disputed results / rigging accusations | Medium | Vote matrix + seeded replay (DISC-4, CORE-7) |
| Forged/replayed `initData` (WebApp) | High | HMAC verify vs DB token per bot, ≤1 h freshness, short-lived signed session (WEB-1) |
| XSS via mirrored group text in WebApp | High | sanitize to text nodes + strict CSP (WEB-5) |
| State polling load on API | Medium | rev-based long-poll, diff responses, per-room cache (WEB-2) |
| WebView fragmentation / old clients | Medium | keyboard fallback always rendered next to launch buttons (WEB-5, rule 12) |
| Rating farming / smurfs / win-trading | Medium | human-quota ranked gate, provisional period, Elo expectation zeroes weak-lobby farming; anomaly flags → moderation only (RAT-2/4, ADV-2) |
| Facts log corruption breaks ratings | High | append-only arch test; crowns rebuildable from log at any `formula_version` (RAT-1/5) |
| Mark-toggle spam floods writes | Low | per-user write cap + idempotent SET; overlay store keeps snapshot hot path cold (ROOM-20) |
| Polish scope creep delays M2 | Medium | wave discipline; polish gate is a finite checklist (`interface-ux.md` §16), unfinished extras hide behind kill-switches (OPS-4) |
| Public rooms attract trolls | High | report queue + global bans + input hygiene (MOD-1..3); AFK already capped by freeze (DISC-1) |
| Telemetry privacy backlash | Medium | aggregate-first schema, documented retention, opt-out via `/delete_me` (OPS-1, MOD-4) |
| Silent production breakage | High | throttled owner-chat alerts on DLQ/error-rate/stalls (OPS-2); restart recovery drills (RUN-3) |

## 12. Acceptance Criteria

Functional:

- [ ] Same engine plays group, interface, and mixed public-room games
- [ ] Mirroring starts/stops exactly with the game; restart-safe
- [ ] Bots fill seats convincingly; zero info-leak incidents in audits
- [ ] All 4 locales render fully; parity test green
- [ ] 16-role catalog with count-based presets validated; mandatory-role locks enforced
- [ ] Freeze/sleepy discipline works end-to-end and persists
- [ ] Any result replayable from artifacts (seeded RNG log + vote matrix)
- [ ] No phase blocks longer than one timeout on a single absent human
- [ ] Zero SQL from enablement on hot webhook path
- [ ] Every WebApp screen has a keyboard equivalent; games completable without opening the app
- [ ] Forged/replayed `initData` rejected; private views never served cross-user over HTTP
- [ ] Crowns fully replayable: `mafia:ratings:rebuild` reproduces identical ratings from the facts log
- [ ] Incentive balance holds (property tests): weak-lobby wins ≈ +0 for top players, high-tier games swing hardest, leaver always penalized
- [ ] Ranked eligibility enforced: private/training/bot-heavy games never change crowns
- [ ] Pencil marks render only to their author on every surface; toggling never bumps public `rev` nor wakes other players' sync

Release-1 polish gate (M2):

- [ ] Fresh user: `/start` → sitting at a table in ≤ 2 taps; < 60 s KPI verified by the OPS-1 funnel, not vibes
- [ ] Fresh bot install: owner follows the README runbook only → playable in < 5 min (ONB-3)
- [ ] Every §12 KPI computable from telemetry; no message content ever stored
- [ ] Report reaches the owner with replayable context; a globally banned user is blocked at every entrance
- [ ] `/delete_me` leaves zero rows for the user; `/privacy` text matches actual storage
- [ ] Load gate: 5 concurrent 15-player games, p95 action < 500 ms, zero lost actions, soak leaves no residue
- [ ] Any guarded feature flips via kill-switch without deploy; failClosed rollback drilled
- [ ] WebApp feels native: theme-synced both themes, haptics on actions, reconnect banner — keyboard parity still green

Operational KPIs (`playability.md` §6):

| Metric | Target |
|---|---|
| /start → first game sitting (new user) | < 60 s |
| Quickplay fill time (empty pool) | ≤ 30 s to bot-fill |
| Games abandoned mid-phase | < 5% |
| AFK freeze incidence after week 1 | declining trend |
| Ghost-chat engagement (dead sending ≥1 msg) | > 40% |
| Median session length | 25–45 min (2–3 games) |
| Concurrency | 5+ simultaneous games |
| Average game length | 15–20 min; <1% action error rate |
