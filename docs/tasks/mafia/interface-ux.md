# Mafia Module — Interface & UX Specification

> UI/UX reference spec for the Mafia module. Features are tracked by stable IDs in
> `todo.mafia.md` (rev 9) — this file defines *how it looks/behaves*, the plan tracks
> *what/when*. All strings referenced here are keys in `lang/<locale>/ui.json`;
> role mechanics live in `roles.json`. Covers games played in a group chat, in the bot's
> private interface ("rooms"), or mixed.

## 0. UX Principles

1. **One primary action per screen.** Every keyboard has a single obvious main button; all
   others are secondary (row order encodes priority).
2. **The Game Card is always current.** One persistent message per player is *edited* in place
   as phases change (never re-sent), so scrolling up never shows stale state.
3. **Seat numbers are identity.** Players are addressed by seat number + name everywhere
   (`3. Мария`); buttons carry seat numbers so two players with equal names stay unambiguous.
4. **Feedback is instant and silent.** Every callback gets an immediate `answerCallbackQuery`
   toast; nothing important is communicated by toasts alone.
5. **Dead ends are forbidden.** Any error toast explains what *to do next*, not just what failed.
6. **Privacy by layout, not by promise.** Anything vote/role-related renders only in DM /
   interface chats; group messages never contain hidden information.

## 1. Entry Points

| Entry | Where | Result |
|---|---|---|
| `/play` or `/mafia_start` | group | Group room created; members declare via `/play` |
| `🎮 Играть` (`interface.play_button`) | bot DM | Main menu → create/join room |
| `📋 Комнаты` (`interface.rooms_button`) | bot DM | Public room list |
| deep link `t.me/<bot>?start=mafia_room_<id>` | anywhere | Opens room card in DM |

## 2. Main Menu (bot DM)

```
🎭 Мафия
Играйте с людьми или с ботами — в группе или прямо здесь.

[🎮 Играть]      [📋 Комнаты]
[📊 Статистика]  [⚙️ Настройки]
[📖 Как играть]
```

- `🎮 Играть`: if the user has no active game → quick actions: create room / join last room /
  open room list.
- If the user is frozen (`freeze.frozen_notice`), Play shows the freeze card instead of menus.

## 3. Room List

```
📋 Открытые комнаты

🌍 Ночь урожая — 4/10 · 🟢 набор
🔒 Пятничный клуб — 7/12 · 🟢 набор
🌍 Быстрые — 11/11 · 🔴 идёт

[🔄 Обновить] [➕ Создать комнату]
[⬅️ В меню]
```

- Shows **public rooms** of this bot + private rooms the user belongs to.
- Rows render via `rooms.room_row`; running rooms are visible but not joinable.
- List is rebuilt on `🔄 Обновить` (no auto-refresh — explicit beats magic).

### Room card

```
🏠 Ночь урожая
👑 Хост: Андрей
👥 Игроков: 4/10 (лимит 6–10)
🎭 Роли: 🎩 🔪 🔍 💉 💋 👁️ …
🌍 Публичная · 🟢 набор

[🎮 Присоединиться]  [🚪 Покинуть комнату]
[▶️ Начать игру]     (host only)
[🥾 Кикнуть игрока]  (host only, lobby only)
```

Joining rules (all enforced server-side, toasts explain refusal):
- lobby only — after start `rooms.join_started_toast`;
- capacity — `rooms.join_full_toast`;
- not frozen — `rooms.join_frozen_toast`;
- one active game per user — `errors.already_in_other_game`.

## 4. Room Creation Wizard (DM)

Step-by-step forced-reply flow; every step is cancellable:

1. **Title** — `rooms.title_prompt`, `/skip` = auto name from lang pack.
2. **Player range** — `rooms.players_range_prompt`, format `min-max`, validated against
   global 5–15; invalid → `rooms.players_range_invalid`.
3. **Roles** — checkbox keyboard built from `roles.json` catalog:
   `[✅ 🔎 Детектив] [⬜️ 💉 Доктор] …` toggles in place (editMessageReplyMarkup);
   mandatory roles (🔪 Mafia ≥1, 🔍 Detective) render locked with ✅;
   `✔️ Готово` validates the set against constraints (mafia share ≤ N/3, solo killers ≤ 2,
   specials ≤ N − civilians_min); failure → `rooms.roles_invalid_toast` with reason.
4. **Visibility** — default: DM-created room = public (visible in list), group-created =
   private (group members only). Toggle available for group rooms
   (`rooms.visibility_button`) **only while the source Telegram group itself is public** —
   the room then appears in everyone's list and outsiders join with ordinary interface
   mechanics (room card / list / deep link / quickplay); otherwise the toggle is refused
   with an explanatory toast. Remote (interface-only) seats have no direct group voice —
   their messages are posted into the group by the host bot («ведущий») with attribution
   (§5).

## 5. Group Game Lifecycle

```
host: /play (in group)
  └─ bot: lobby card + [🚪 Выйти] [🤖 Добавить ботов] [🥾 Кикнуть]
          + [▶️ Начать игру] (host only)
members: /play → declared (explicit command opt-in only — no join button in groups;
          card buttons manage the lobby, they never seat anyone)
  └─ DM check: if the member never started the bot:
       group: interface.dm_required (@mention)
       DM: ready-check card (lobby.ready_check_dm) → ✅ confirms channel works
  └─ host's ▶️ Start stays disabled until every human confirmed DM
start → roles dealt to DM/interface → mirror ON → game runs → end → mirror OFF
```

- Mirroring (`MirrorService`): every non-command group message during a running game is copied
  into each participant's interface feed as `interface.feed_from_group`. Commands, bot messages
  and media captions are skipped. Fan-out goes through the platform outbound queue
  (`TgSenderContract`) — rate limiting is the pipeline's job, not the module's.
- Mirroring starts at game start and stops at game end/cancel — never before, never after.
- Interface-only participants (public group room): speak via `🗣 Сказать`; the relayed text is
  posted to the group on their behalf (`feed_from_interface` style attribution).

## 6. In-Game Interface Chat (every participant, DM)

Layout discipline — exactly three kinds of messages:

1. **Game Card** — one message, edited on every phase change, contains everything current:

```
🌙 Мафия · Ночь 2 · ⏳ 00:45
──────────────────────
1. Андрей        😴
2. 🤖 Барон
3. Мария         💀
4. Тихий_2
──────────────────────
🎭 Вы: 💉 Доктор

[💉 Лечить этой ночью]  [⏭ Пропустить]
```

   - header = `meta.game_signature` + `interface.game_card_header`;
   - roster rows = `lobby.player_row`, markers: 💀 dead, 😴 missed-vote badge (this day),
     🤖 bot;
   - own role line = `interface.card_you_line`;
   - action row swaps per phase/role (night menu / vote entry / "you sleep" hint).

2. **Feed** — mirrored group messages and interface relays (see §5), plus event images
   (§8). Feed messages are plain sends; they are never edited.

3. **Action Screens** — transient keyboards opened under the Game Card:

Night target selection (role-specific prompt key `night.prompt_*`):
```
💉 Кого лечим этой ночью?
[1 Андрей]  [2 🤖 Барон]
[3 Мария]   [4 Тихий_2]
[💉 Себя (осталось: да)]
[⏭ Пропустить ход]
```
Tap → confirm step (`night.confirm_template` + Подтвердить/Отмена) → cast toast
(`night.cast_toast`) → Game Card action row switches to "chosen ✓ (tap to change)".

Day vote screen:
```
🗳 Голосование! Кто мафия?          (day.voting_open)
1. Андрей  ██ 2
2. 🤖 Барон █ 1
3. Мария    ·
⏳ Осталось: 30 сек.

[1 Андрей] [2 🤖 Барон] [3 Мария]
[🚫 Воздержаться]
```
Live board edits are throttled (≥3 s between edits, or on-change batched); secret-ballot mode
shows counts without voter names until close (`day.secret_note`).

Speaking relay (interface-only or quiet mode):
`🗣 Сказать` → forced reply `interface.say_prompt` → next single message is relayed to all
feeds (+ group for public rooms) with author attribution.

## 7. Host Tools

- **Kick** (lobby only, host only): `/kick` in group or `🥾 Кикнуть игрока` →
  member picker (`kick.menu_title`) → confirm (`kick.confirm_template`) →
  broadcast `kick.done` + DM to the kicked player (`kick.kicked_dm`).
- **Add bots**: fills seats toward `max_players` (or +1 increments); nickname factory avoids
  collisions (`bot_players.json → names.pool`, suffix `_N`).
- **Replace leaver mid-game** (setting, default on): `admin.replace_leaver_button` swaps the
  departed human for a fresh bot inheriting their role knowledge strictly per fairness rules.
- **End early**: `admin.end_confirm` → reveal-all summary.

## 8. Event Images (PNG assets)

Sent via `sendPhoto` with localized caption keys; uploaded once per bot, then re-sent by cached
`file_id` (asset table maps `event -> file_id` per locale-independent image).

```
resources/images/
├── lobby_banner.png        # caption: lobby.created
├── night_falls.png         # caption: night.phase_announce
├── morning_death.png       # caption: night.death_result
├── morning_quiet.png       # caption: night.no_kill_result
├── voting.png              # caption: day.voting_open
├── eliminated.png          # caption: day.eliminated
├── win_town.png            # caption: end.town_win
├── win_mafia.png           # caption: end.mafia_win
├── win_solo.png            # caption: end.solo_win
├── satanist_win.png        # caption: end.satanist_win
├── badges/sleepy.png       # inline sticker-style reply when badge assigned
└── roles/{role_id}.png     # 16 role cards; caption: roles.your_role_header + intro
```

Style guide: flat illustration, dark base + one accent color per team (town=amber, mafia=crimson,
solo=violet), no text baked into images (text comes from captions → free i18n).

## 9. End-of-Game Screen

```
🏆 Игра окончена!
🏙 Победа города!
📋 Роли:
🎩 Дон — 🤖 Барон
🔪 Мафия — Мария
🔍 Детектив — Андрей …
⏱ Партия длилась 18 мин., дней: 3.
😴 Сони: Андрей, Тихий_2

[🔁 Реванш!]  [📊 Полная статистика]
```

Rematch recreates the room with identical settings and re-invites the same humans (bots are
regenerated). Discipline counters update here (see plan §Discipline).

## 10. State, Idempotency, Recovery

- Every action screen carries `gameId+phaseNumber`; stale taps answer `errors.stale_action_toast`.
- Actions dedupe on `(userId, gameId, phaseNumber, actionType)` — double-tap is idempotent
  (`errors.double_action_toast`).
- Bot restart: snapshots restore, overdue deadlines advance lazily, mirrors resume only if the
  game is still running (mirror flag lives in the snapshot).
- All renders go through the same presenter event contract as group output — one engine,
  two skins (see plan §Architecture).

## 11. Rev-4 Additions (competitive learnings)

Strings live under the `extras.*` key block in every locale pack.

### Ghost chat 👻
On elimination the player's Game Card switches to spectator mode
(`extras.ghost_spectator_hint`) and a **Ghost Chat** surface opens (`extras.ghost_chat_title`,
welcome line `extras.ghost_welcome`). Ghosts see the public game feed and speak among
themselves; nothing reaches the living until the reveal. Implementation: same relay pipeline,
audience filter = dead seats only.

### Ready-skip ✅
During Night and DayVoting the Game Card action row gains `extras.ready_skip_button`. When
**every required actor** for the phase is either acted or readied, the phase closes immediately
(`extras.phase_skipped_early_toast`). Readiness renders as `extras.ready_mark_on_card` next to
the seat. Rule: skip never bypasses a role that still *must* act (e.g., mafia without any
submitted kill may still be closed only by timeout, unless all mafia readied).

### Pause/resume ⏸
Host (fallback: chat admin) freezes deadlines: marker on the card (`extras.paused_marker`),
notice with resumer name (`extras.paused_notice`), resume via `/mafia_resume` or button
(`extras.resume_button`, toast `extras.resumed_toast`). Snapshot stores `pausedAt`; on resume
`deadlineAt += pausedDuration`, mirrors keep flowing but actions are rejected while paused.

### Vote matrix 📊 + share card 📤
End screen gains two buttons. **Vote matrix** renders the full grid
(`extras.vote_matrix_title`, rows `extras.vote_matrix_row`) — the transparency artifact for
disputes. **Share** posts the pre-rendered PNG summary to the chat with caption
(`extras.share_card_caption`) containing the room deep link — the organic invite loop.

### Personal notes 📝
`extras.notes_button` on the card opens a per-seat menu (`extras.notes_menu_title`,
`extras.note_for_seat`) → forced reply stores a private note; confirmation
`extras.notes_saved_toast`. Notes are visible only to their author, live in the snapshot's
private section, never enter feeds.

### Speech cooldown
Relayed speech enforces a per-user cooldown; a rejected attempt answers with
`extras.cooldown_toast`.

### First-run tutorial & rules
On first `/play` ever (profile flag) the bot sends a one-time localized primer
(`extras.tutorial_title`) walking through roles/actions samples. `/rules` always available:
inline paginated rules rendered from lang packs (`extras.rules_command_hint` advertised in menus).

## 12. Competitor UX Patterns Adopted (rev 5)

Source mapping and rationale: `ui-patterns.md`. Strings under `extras2.*`.

### 12.1 Game Card v2 — glanceability upgrade
Header gains two chips; board rows gain tally bars:

```
🌙 Мафия · Ночь 2 · ⏳ 00:45 · День 2
──────────────────────
1. Андрей ✅   🟢
2. 🤖 Барон
3. Мария 💀    ❗️
──────────────────────
🎭 Вы: 💉 Доктор

[💉 Лечить] [⏭ Пропустить] [✅ Готов]
```

- Timer chip `extras2.timer_chip`: coarse updates every 15 s only (edit-budget safe).
- Progress chip `extras2.phase_progress_chip` («День 2»).
- Ready marks `✅` from ready-skip; HUD dots are **private-view only**: 🟢/🔴 = your manual
  notes, ✔️/❗️ = auto-marks from your own detective/journalist results. Never rendered in
  group surfaces.
- Vote board rows become `board_row + bar`: `{name} █████░ {votes}` (bar width ∝ votes/max,
  max 10 cells).

### 12.2 Seat inspector 🔎
`extras2.seat_inspector_button` on the card → seat picker → private panel
(`extras2.inspector_title`): your notes for the target, your check history against them,
their votes this game (`extras2.inspector_votes_line`). Pure read surface, zero leaks.

### 12.3 Emergency assembly 🆘 (Among Us nod)
During DayDiscussion any alive player may press `extras2.emergency_button`: remaining
discussion time is dropped, voting starts now. Budget: 1 per player per game, ≤2 per game
(`extras2.emergency_used_toast`, exhausted → `extras2.emergency_none_left_toast`). Night is
never interruptible.

### 12.4 Ghost predictions 👻
Eliminated players get `extras2.ghost_predict_button` in ghost chat: they pick the expected
winner; correct calls count into a profile stat («прозорливость») shown at reveal. Dead stay
busy, zero info risk.

### 12.5 Training mode 🎓
Main menu third button `extras2.training_button` → instant interface room where every other
seat is a bot (roles rotate each round). No freeze strikes, no stats recorded, exit anytime.
Onboarding funnel for newcomers before they face humans.

### 12.6 Opt-in phase pings 🔔
Settings toggle `extras2.ping_toggle_button`: when ON, the user gets a silent DM ping at phase
changes of their active game. Default OFF — group @-spam stays out.

### 12.7 Avatar identity 😎
Profile picker `extras2.avatar_pick_button` (emoji pool shared with bot personas); chosen emoji
prepends the name in rosters, feeds and share-cards. Cheap personalization, no P2W.

### 12.8 Wills ✍️ (optional preset, default off)
Room setting enables `extras2.will_prompt_dm`: night victims may pre-write one message,
published with the morning death announcement. ToS-flavor drama without balance impact.

### 12.9 Event log 📜
`/mafia_log` returns the last ~20 **public** events (deaths, phase changes, vote closes,
saves hinted as «кто-то был спасён»). Rejoiner catch-up without leaking hidden state.

### 12.10 Delight watchlist
Native TG reactions (👍😱💔) on event images — DTO support confirmed (`setMessageReaction`);
promoted to the registry as IMG-5 (W6). Cosmetic only, never required for flow.

## 13. WebApp Presenter (planned W7) — registry: WEB-1..5

Telegram Mini App (HTML) as the third presenter: a progressive enhancement that removes the
hard limits of the keyboard surface. Keyboards stay canonical (design rule 12 — keyboard
parity); the WebApp never gates a flow.

### 13.1 Why a separate window

| Keyboard limit | WebApp answer |
|---|---|
| editMessageText throttling (≥3 s) + floodwaits on live boards | own render loop, zero edit limits |
| forced-reply chains (room wizard, notes, wills) | real form controls, one screen |
| ASCII HUD dots / bars | real components (badges, grids, progress) |
| ghost chat via relay messages | normal chat panel |
| 24-char button labels | full typography |

### 13.2 Launch points & fallback

- `web_app` inline buttons (private chats) — always paired with a callback equivalent row.
- `MenuButtonWebApp` optional per-bot setting («🎭 Открыть игру»).
- Direct link `t.me/<bot>?startapp=mafia_room_<roomId>` — same deep-link grammar as keyboard
  rooms.
- Capability detection: if `window.Telegram?.WebApp` is missing or `initData` is empty →
  render the keyboard-fallback notice (`webapp.unavailable_toast`) and keep the chat flow.

### 13.3 Auth & session (WEB-1)

1. Client obtains `initData` from the Telegram WebView.
2. Backend verifies HMAC-SHA256 against the **bot token from DB for the room's `bot_id`**
   (multi-bot: never a single `../../../../../../.env` token), checks `auth_date` freshness ≤ 1 h.
3. Server issues a short-lived signed session (Laravel signed value, ~30 min TTL, sliding
   renewal) — `initData` is never re-sent on subsequent requests.

### 13.4 State sync (WEB-2)

- `GET /tg/webapp/mafia/state/{roomId}` →
  `{public: PublicStateView, private: PrivateView, notes, rev, notesRev}`.
- Client long-polls with `rev`; server holds ≤ 25 s until `rev` (public state) or the
  caller's own `notesRev` (pencil marks) bumps, else returns 204-style empty. Plain polling
  fallback (5 s) if long-poll fails twice.
- `rev` is the snapshot's monotonic counter (CORE-2); `notesRev` is the private overlay's own
  counter (ROOM-20) — marking a suspect never wakes other players.
- Pencil-mark toggles POST to a light single-writer path (same auth, no game lock).

### 13.5 Actions (WEB-3)

All writes POST into the same ingress pipeline as callback queries: same dedupe keys
`(user, game, phase, action)`, same locks, same validation errors as toasts → JSON errors.
No business logic outside the shared path.

### 13.6 Screen set v1 (WEB-4) — parity checklist

- [ ] Game Card (header chips, roster with markers, own role line) — mirrors §6 layout
- [ ] Night action forms per role (target picker + confirm) — mirrors §6 action screens
- [ ] Day vote grid with live tally — mirrors §6 voting board, no throttling
- [ ] Pencil-marks editor: tap seat badge → palette popover (§15) — sudoku-style stacking
- [ ] Notes editor + seat inspector — mirrors §12.2–12.3 private layer
- [ ] Ghost chat panel + predictions — mirrors §11 ghost features
- [ ] End screen + vote matrix + share — mirrors §9
- [ ] Room creation as ONE form — mirrors §4 fields, same validation, same toasts
- [ ] Simplified-formatting toggle honored (registry ROOM-17) — WebApp respects it

Every screen: same string keys from `lang/<locale>/ui.json` — no separate WebApp copy.

## 14. Crowns, Tiers & Championships (planned W8) — registry: RAT-*

Ranked play exists in public rooms only; rating unit is the crown 👑 (integer). Strings live
under the `rat.*` key block in every locale pack.

### 14.1 Rendering rules

- **Crown badge** next to the name wherever names render: roster rows (`lobby.player_row`
  gains `{crown}` placeholder), feed attributions, end screen, share card PNG, WebApp.
  Format: `👑 234`; provisional players render `👑 ?` until their first N ranked games land.
- **Tier line** on lobby card, room card and room list row: `🏆 Ранг 3` — computed at deal
  from seated crowns (RAT-3); unranked lobbies show no tier line.
- **Ranked marker** on lobby creation: public rooms default ranked; a small toggle hides it
  («не рейтинговая») for casual sessions.

### 14.2 Surfaces

| Surface | Content |
|---|---|
| Main menu | new row `[🏆 Рейтинг]` → leaderboard screen |
| Leaderboard screen | top-N of current season (`rat.board_title`, rows `rat.board_row`), own position pinned at bottom |
| Room list / card | tier line + entry gate: rooms with min-crowns requirement show `👑 от 200`; joining below threshold → `rooms.join_low_rating_toast` |
| Championship | banner block atop the room list while active; bracket screen (`rat.bracket_title`) with rounds/advances; champion title on the season board |
| End screen (ranked) | crown delta line per player: `👑 +12 / −7` (RAT-4 output) |

### 14.3 Principles

- Deltas, not totals, are the emotional payload: the end screen always shows what changed.
- Never color-only rank signals — badge + number (accessibility rule 9).
- The leaderboard is factual and season-scoped; no global all-time shame board.

## 15. Pencil Marks — private suspicion layer (W5 chat / W7 WebApp) — registry: ROOM-13/20/21

The sudoku metaphor, applied to a social-deduction table: every seat can carry a stack of
small "pencil" icons — quick, reversible, private suspicions — instead of forcing players to
write text notes. Marks are **strictly owner-visible** on every surface.

### 15.1 Palette (fixed v1, extensible via package settings)

| Mark | Meaning | Source |
|---|---|---|
| 🔪 | «моя мафия?» — suspect | manual |
| 🟢 | verified innocent in my book | manual |
| ❓ | doubt / undecided | manual |
| ⭐ | my vote candidate for today | manual |
| ✔️ | your own check: innocent | auto (detective/journalist results) |
| ❗️ | your own check: mafia | auto |

Rules:

- **Stacking**: multiple marks per seat coexist (like pencil digits in a sudoku cell); fixed
  render order = auto first, then 🔪 → ⭐ → 🟢 → ❓.
- **Inline cap**: the Game Card roster shows ≤3 marks per seat; the full stack lives in the
  seat inspector and the WebApp popover.
- **Lifecycle**: editable while alive; wiped at game end unless persisted to profile
  (`settings.persist_notes`); never enters feeds, share cards, or other players' views.
- Auto marks are read-only — engine-written when your own check result lands.

### 15.2 Interaction paths

- **Chat (ROOM-21)**: `🔎 Инспектор` → seat → palette row of toggle chips; tap toggles
  on/off. Roundtrip ≤2 taps from the card. Inline cluster renders next to the seat name:
  `3. Мария 🔪⭐`.
- **WebApp (WEB-4)**: tap the seat badge → palette popover with the same chips + free-text
  note field; changes apply instantly (optimistic UI, reconciled by `notesRev`).

### 15.3 Storage & sync contract (ROOM-20)

- Store key `mafia:notes:{roomId}:{userId}` — outside `GameSnapshot`, own `notesRev`;
  atomic single-writer writes, no game lock, no public-`rev` bump (other players' long-polls
  never wake).
- Presenter composes YOUR card view by reading your overlay at render time; the firewall arch
  test asserts the store API is user-scoped and presenters never read another player's key.

## 16. Release-1 Polish Bar (M2 exit gate) - registry: ONB / MOD / OPS / WEB-6..8

«Конфетка с первого релиза» is a checklist, not a vibe. M2 ships only when every line below
is green; anything unfinished hides behind a kill-switch (OPS-4), never half-visible.
Strings live under `extras4.*` (`onb.*`, `mod.*`, `ops.*`) in every locale pack.

### 16.1 First contact

- [ ] `/start` welcome: Quickplay / Rooms / Training / Rules / Language — ≤2 taps to a table (ONB-1)
- [ ] Deep links (`?start=mafia_room_*`, `?startapp=`) land on the target surface, never a dead menu (ROOM-5, WEB-5)
- [ ] Training room plays a full game vs bots with zero profile writes (ROOM-14)
- [ ] Bot profile packaged: commands menu, localized description/about, start attachment (ONB-3)
- [ ] Coach hints cover the first 3 games; role encyclopedia reachable from the deal reveal (I18N-6, ONB-2)

### 16.2 Feel & resilience (WebApp, W7)

- [ ] `themeParams` synced in both themes; safe-area insets respected (WEB-6)
- [ ] Haptics on cast/vote/mark-toggle; skeletons on deferred state; BackButton navigates screens (WEB-7)
- [ ] Connection loss shows a stale banner, auto-reconnects, reconciles by `rev`/`notesRev` (WEB-8)
- [ ] Keyboard parity untouched — app closed ≠ game blocked (rule 12)

### 16.3 Trust & safety

- [ ] Report ⚠️ one tap away on every public surface; owner receives replayable context (MOD-1)
- [ ] Ban list blocks join / quickplay / deep-link entrances alike (MOD-3)
- [ ] `/privacy` truthful about storage and retention; `/delete_me` purges everything (MOD-4)
- [ ] Hostile input renders safe in all locales (MOD-2, escaping tests)

### 16.4 Operations

- [ ] Funnel/KPI dashboard answers every §12 metric from live events (OPS-1)
- [ ] Alerts reach the owner chat on DLQ depth / error spike / stalled phases (OPS-2)
- [ ] Nightly load+soak green: p95 < 500 ms, zero lost actions, Redis baseline restored (OPS-3)
- [ ] Runbook dry-run passed; rollback = module disable, drilled (OPS-6)
