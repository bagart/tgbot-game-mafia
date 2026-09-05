# Mafia Module — Competitive & Review Analysis

> **ARCHIVED (rev 7).** Rationale input only — every adopted item lives in the feature
> registry of `todo.mafia.md` (see Doc Map §0). Do not use as a live spec.

> Input for `todo.mafia.md` rev 4. Sources analyzed: Telegram mafia/werewolf bot ecosystem
> (MafiaBot-family, city-mafia projects, werewolf bots), the long-lived Discord Werewolf bot,
> social-deduction design patterns from commercial titles (Town of Salem, Goose Goose Duck,
> Among Us), and recurring complaint themes from public user reviews.

## 1. Review Pain-Point Matrix

| # | Complaint theme (reviews) | Root cause | Our answer | Status |
|---|---|---|---|---|
| 1 | «Мёртвым скучно, выходят из игры» | dead = zero agency for 20–40 min | Ghost chat (dead-only feed), spectating is a show | **NEW → Phase 2** |
| 2 | «Зал пуст, никто не заходит» | lobbies need humans now | Filler bots (instant full table), public room list, share-cards | planned |
| 3 | «АФК-игрок держит всех на таймере» | phase waits on one person | Ready-skip acceleration + replace-with-bot + freeze | planned / **NEW skip** |
| 4 | «Хост вышел — игра умерла» | host-bound state | Auto host reassignment; **NEW:** pause/resume survives host change | reassigned ✓ / **NEW pause** |
| 5 | «Не понял свою роль / что делать» | poor onboarding | Role card with goal + examples; **NEW:** first-run tutorial DM, `/rules` inline wiki | role cards ✓ / **NEW tutorial** |
| 6 | «Бот спамит группу» | naive message-per-event | Single live-edited game card, mirrors batched, **NEW:** speech cooldowns | planned / **NEW cooldown** |
| 7 | «Непонятно, кто за кого голосовал» | opaque ballots | Open-ballot live board; **NEW:** end-of-game vote matrix | planned / **NEW matrix** |
| 8 | «Меня кинули, доказать не могу» | no evidence trail | Audit log + **NEW:** reproducible results (seeded RNG, vote matrix export) | partial / **NEW** |
| 9 | «Игра слетела — всё пропало» | volatile state | Redis snapshots + lazy resume | planned |
| 10 | «Боты либо тупые, либо читят» | brain sees everything | Fairness firewall (views-only brains) + personas | planned |
| 11 | «Нет статистики, нечего качать» | no progression | Profiles, wins, соня-badge totals; **NEW:** ELO/seasons/achievements | partial / **NEW** |
| 12 | «Альты портят игру» (smurfs/griefers) | anonymous accounts | **NEW:** reports + moderator karma; per-chat bans | **NEW** |
| 13 | «Правила у всех разные» | rigid presets | Checkbox role builder; **NEW:** shareable preset codes | builder ✓ / **NEW codes** |
| 14 | «Таймеры давят / тянутся» | fixed durations | Configurable timings + ready-skip | planned |

## 2. Steal-List (prioritized adoption)

### P0 — adopt into MVP / Phase 2
1. **Ghost chat** 👻 — eliminated players keep a private feed; they see the public game and can
   talk among themselves; nothing reaches the living until the reveal.
2. **Ready-skip** ✅ — any phase closes early when every required actor pressed “готов”; solves
   both timer-stress (#14) and AFK-wait (#3).
3. **Pause/resume** ⏸ — host (or fallback admin) freezes deadlines; snapshot stores
   `pausedAt`; resume shifts `deadlineAt`. Survives restarts.
4. **Vote matrix** 📊 — end screen renders the full who-voted-whom grid; the #1 dispute killer.
5. **Shareable result card** 📤 — pre-rendered PNG summary (winner, roles, MVP) posted to the
   group on demand; doubles as the organic invite loop (deep link inside caption).
6. **Personal notes** 📝 — private per-target notes in DM (“Мария: голосовала против Андрея”),
   visible only to the author, wiped at game end unless persisted to profile.
7. **Speech cooldowns** — relayed interface speech limited (e.g. 1 msg / 10 s / user);
   protects groups from fan-out floods (#6).
8. **First-run tutorial + `/rules`** — one-time DM walkthrough (role cards sample) and an
   always-available inline rules page, localized from lang packs.

### P1 — Phase 3 differentiators
9. **Provable fairness** — before dealing: publish `SHA-256(serverSeed)`; after the game:
   reveal serverSeed + client-includable formula. Verifiable “бот не читил” without trusting us.
10. **Progression** — seasons, ELO-like rating, achievements (соня-count, first-blood detective,
    pacifist town win…), weekly leaderboards per bot.
11. **Tournaments** — bracket mode with TO (tournament organizer) role: bulk-create rooms,
    advance winners, export results.
12. **Reports & karma** — in-game report button → moderation queue; karma decays; low-karma
    players restricted from hosting public rooms.
13. **Preset share-codes** — room role-config serialized to a short code; others import it in
    the creation wizard (`rooms.import_code`).

### P2 — watchlist (do not build yet)
14. **Telegram Mini App client** — real UI instead of button menus; powerful but heavy.
    Re-evaluate once the button-interface stabilizes; architecture already isolates presenters,
    so a WebAppPresenter slots in without touching the core.
15. **Spectator links** — read-only feed for outsiders of public rooms (privacy review needed).

## 3. Deliberately NOT Copying (common monetization dark patterns)

- Energy systems / “next game in 30 min” walls.
- Paid roles or paywalled presets (pay-for-power kills trust in a deduction game).
- Forced channel-subscription gates before playing (retention poison).
- Loot-box cosmetics in game surfaces; ads injected into feeds.

Monetization stance: cosmetics-only, outside game surfaces, never gameplay-affecting.

## 4. Derived Design Rules (add to review checklist)

1. **No single point of waiting**: every blocking phase has an accelerator (ready-skip,
   replace-bot, auto-advance). Nothing waits longer than one timeout on one absent human.
2. **Every dispute resolvable from artifacts**: seeded RNG + audit log + vote matrix ⇒ any
   result can be replayed and shown.
3. **Boredom budget ≤ 20 s**: a player either has an available action or a show to watch
   (feed events, ghost chat) within 20 seconds, or the flow is wrong.
4. **Growth loops are user-initiated**: share-cards and deep links, never forced gates.
5. **One surface of truth per player**: the live game card; auxiliary messages are ephemeral.
