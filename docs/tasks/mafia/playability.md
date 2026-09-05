# Mafia Module — Playability Pass

> **ARCHIVED (rev 7).** Rationale input only — every adopted item lives in the feature
> registry of `todo.mafia.md`; KPI table merged into its Acceptance Criteria §12.
> Do not use as a live spec.

> Third improvement round (`competitive-analysis.md` covered features/discipline,
> `ui-patterns.md` covered interface mechanics). Focus here: **flow friction, pacing,
> retention, emotional design**. Adopted items land in `todo.mafia.md` rev 6 phases and
> strings under `extras3.*`.

## 1. Friction Map (where players leak out)

| Moment | Friction | Countermeasure | Phase |
|---|---|---|---|
| Opened bot, no friends online | «некому играть» | ⚡ **Quickplay**: matchmake into any open public room (locale-matched); empty pool → bot-fill countdown | 2 |
| Lobby waits forever | dead air | bot-fill countdown already planned; show ETA («наберём ботов через 30 с») | 2 |
| Discussion drags / too short | pacing whiplash | host **⏱ +30 сек** once-per-phase extension; emergency assembly covers the opposite | 2 |
| Newbie frozen at first night action | fear of mistake | **Coach hints**: contextual tips in action screens, first 3 games only, dismissible | 2 |
| Left mid-session, came back later | lost surface | **↩️ Вернуться в игру** deep link on profile/main menu while a game is running | 2 |
| Room settings overwhelm newcomers | choice paralysis | **Templates**: «Классика», «Молния» (blitz timings), «Турнирный» — one tap, still editable after | 2 |
| Game ended, nobody knows who carried | flat ending | **Peer MVP voting** + nominations (лучший ход, лучшее вскрытие) on end screen | 2 |
| Same faces order every game | meta-gaming by seat order | optional **🔀 перемешать места** at deal | 2 |
| Win streaks invisible | no table reputation | 🔥 streak badge next to name in roster (public, factual) | 2 |
| Small screens choke on formatting | readability | ♿ simplified formatting toggle (no bars/spoilers, shorter lines) | 2 |
| Community plays nightly, recreates rooms daily | ritual friction | **📅 Scheduled rooms**: cron-opened public rooms with fixed config («каждый вечер 20:00») | 3 |

## 2. Retention Loops (beyond ELO/seasons already planned)

- **Role collection**: gallery of the 16 role cards; a card is unlocked the first time you play
  the role (`role_unlocked_toast`). Completionists keep queuing.
- **Daily quests** (cosmetic-only rewards): «выиграй за город», «сделай 2 проверки», «не будь
  соней 3 игры». Shown on main menu (`quests_title`, progress lines).
- **Login streak** feeds quest rerolls — never gates gameplay (monetization stance holds).

## 3. Emotional Design (cheap delights)

- Death-scene art variety: 2–3 PNG variants per event, seeded-random pick.
- 🔥 streak badge (public, factual, capped display).
- End-screen superlatives from peer votes: «Лучший ход», «Лучшее вскрытие», «Тень года»
  (survived as godfather unchecked).
- Morning reveal uses spoiler-wrapped role of the dead (tap-to-reveal micro-drama).

## 4. Data-driven Balance (keeps the meta healthy)

- Telemetry: win-rate & survival per role per count-bucket, fed into a balance dashboard.
- Presets are versioned data (`roles.json`): tuning ships without code changes; changelog in
  release notes so regulars see «мафия ослаблена на 10 игроков» transparently.
- Anomaly flags (collusion suspicion) go to moderation queue only — never auto-punish.

## 5. Accessibility Rules

- Never color-only signals: 🟢🔴 dots always paired with text label in inspector.
- ♿ simplified formatting mode (drop bars/spoilers/ASCII tables; short plain lines).
- Buttons carry seat numbers + names — screen-reader safe ordering.
- Cooldowns limit sends, not typing time.

## 6. Playability KPIs (success criteria additions)

| Metric | Target |
|---|---|
| /start → first game sitting (new user) | < 60 s |
| Quickplay fill time (empty pool) | ≤ 30 s to bot-fill |
| Games abandoned mid-phase | < 5% |
| AFK freeze incidence after week 1 | declining trend |
| Ghost-chat engagement (dead sending ≥1 msg) | > 40% |
| Median session length | 25–45 min (2–3 games) |

## 7. Considered and Deferred / Rejected

- Mid-game role switching events (chaos modes) — fun once, noise later; maybe event-weekend
  preset far future.
- Collab mode 2v2 board — different game, scope creep.
- Auto-kick silent humans mid-game — replaced-by-bot flow already covers; kicking feels hostile.
- Real-money stakes — hard no (legal + trust).
