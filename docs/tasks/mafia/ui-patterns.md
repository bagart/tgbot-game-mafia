# Mafia Module — Competitor Interface Pattern Analysis

> **ARCHIVED (rev 7).** Rationale input only — every adopted pattern lives in the feature
> registry of `todo.mafia.md` and the mockups of `interface-ux.md` (§12). Do not use as a
> live spec.

> Companion to `interface-ux.md`. Focus: **UI/UX mechanics only** (not gameplay) across
> recognizable social-deduction products, what we adopt, how it maps onto Telegram constraints,
> and what we reject. Adopted items land in `interface-ux.md` §12 with strings under
> `extras2.*`.

## Sources

| Product | What its interface is famous for |
|---|---|
| Telegram mafia bots (MafiaBot family, city-mafia projects) | live-edited status post, seat-numbered voting keyboards, confirm-step, DM action menus |
| Discord Werewolf | channel separation (day/night/dead), vote tally bars, @ping phase alerts, role wiki links |
| Town of Salem | wills, personal notes/tags HUD, death scenes, role wheel |
| Goose Goose Duck | rich player cards, cosmetic identity |
| Among Us | **Emergency meeting**, voting reveal screen, ghost play |
| Werewolf Online | card/tab navigation, seat inspector popups, role icon legend, emotes |

## Adoption table

| # | Pattern (source) | Telegram adaptation | Verdict |
|---|---|---|---|
| 1 | Live-edited pinned status post (TG mafia) | our Game Card (single editable message) | already core ✓ |
| 2 | Seat-numbered voting keyboard, 2–3 columns | `seat_button` grid | already ✓ |
| 3 | Confirm-step before irreversible acts (TG mafia) | night confirm template | already ✓ |
| 4 | Vote tally **bars** `███░░` (Discord WW) | render bars in board rows, width ∝ votes/max | **ADOPT (§12.1)** |
| 5 | Timer countdown visible at all times | timer chip in card header, coarse 15 s edits (edit-budget safe) | **ADOPT (§12.1)** |
| 6 | Day/phase progress chip («День 2») | header chip `extras2.phase_progress_chip` | **ADOPT (§12.1)** |
| 7 | @ping on phase change (Discord WW) | opt-in per-user toggle `extras2.ping_toggle_button`; default OFF (anti-spam) | **ADOPT (§12.7)** |
| 8 | Personal notes/tags HUD (ToS) | private HUD dots on YOUR card view: 🟢/🔴 from notes, ✔️/❗️ auto from own checks | **ADOPT (§12.2)** |
| 9 | Seat inspector popup (WWO) | `🔎 Инспектор`: per-seat private panel — notes, your check results, this-game votes | **ADOPT (§12.3)** |
| 10 | **Emergency meeting** (Among Us) | `🆘 Экстренный сбор`: alive player skips remaining discussion → instant vote; 1/game/player, ≤2/game | **ADOPT (§12.4)** |
| 11 | Voting reveal screen (Among Us) | open ballot closes with full who→whom broadcast + matrix button | already partial ✓ (matrix rev 4) |
| 12 | Ghost stays busy (Among Us) | ghost predictions: dead bet the winner, stat «прозорливость» | **ADOPT (§12.5)** |
| 13 | Practice vs AI (many games) | `🎓 Тренировка с ботами`: instant all-bot table, you + fillers, no penalties, exit anytime | **ADOPT (§12.6)** |
| 14 | Cosmetic identity (GGD/WWO) | profile emoji-avatar picker (same pool as bots) prepended to name everywhere | **ADOPT (§12.8)** |
| 15 | Wills (ToS) | optional room preset: night victims leave a will shown at morning reveal | **ADOPT, default off (§12.9)** |
| 16 | Emotes/reactions (WWO) | native TG reactions on event images (👍😱💔) if platform DTO allows; cosmetic only | **WATCHLIST** |
| 17 | Event log/history tab | `/mafia_log` — last 20 *public* events, helps rejoiners | **ADOPT (§12.10)** |
| 18 | Channel separation (Discord WW) | already modeled: group surface / DM surface / ghost surface | covered ✓ |

## Rejected patterns

- Swipe/tab-heavy layouts — impossible in pure Telegram; our three-surface model covers it.
- Voice rooms integration — out of scope, breaks async nature.
- WebApp-first client — deferred (P3 watchlist), presenters isolate us from rework.
- Paid emotes/cosmetics inside game surfaces, ads between phases — monetization stance stands.
- Auto-ping everyone on every phase (Discord default) — spam generator; ours is opt-in.

## Derived UI rules (append to review checklist)

6. **Glanceability**: the Game Card answers “who am I, whose turn, how long” without scrolling.
7. **Private layer is rich, public layer is calm**: HUD dots, inspector, notes never leak into
   shared surfaces.
8. **Recognizable over original**: when a mechanic exists in a famous game, mirror its shape
   (bars, emergency button, ghost busy-work) so players transfer skill instantly.
