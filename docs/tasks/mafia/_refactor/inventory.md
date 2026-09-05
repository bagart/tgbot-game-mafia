# Mafia Docs — Phase A Inventory (A-01..A-03)

Status: COMPLETE (2026-08-24) · Feeds `migration-matrix.md`

## A-01. Document inventory

| File | Size | Declared role (self-description) | Actual state |
|---|---|---|---|
| `todo.mafia.md` | 546 L, rev 9 | Master plan: architecture, feature registry (~120 IDs in 16 groups), waves, data model, acceptance | Dense but monolithic; mixes domain, API-ish contracts, Telegram presentation, UX, ops. Absorbed rev 2–8 content. **To be split completely, then deleted**. (2026-08-26: rev 10 platform-reality pass landed, now 663 L — deltas routed per matrix §1 note; targets unchanged) |
| `interface-ux.md` | 534 L | UI/UX reference spec (§0–§16): screens, mockups, flows, string keys | Richest UX source. Contains real API/state-sync contracts (§10, §13.3–13.5) that belong to the API phase, plus Telegram-specific rendering. Not mentioned in the draft's §1 forbidden list nor §58 deletion map — **added to deletion list by this inventory** |
| `roles.json` | 253 L JSON | Machine-readable role catalog: teams, night resolution order, win-check order, constraints, presets N=5..15 | Canonical DATA. Stays; becomes part of domain/ruleset config (`03-roles/`), eventually packaged into the module repo |
| `competitive-analysis.md` | 85 L | Self-marked ARCHIVED (rev 7). Pain-point matrix + steal-list P0/P1/P2 + rejected monetization | All adopted items already in registry; remaining value = rejected list + design rules 1–5 (duplicated into todo §9). Delete after verifying coverage |
| `ui-patterns.md` | 60 L | Self-marked ARCHIVED (rev 7). Competitor UI pattern adoption table (18 patterns) + rejected patterns | Adopted items live in interface-ux §12. Derived rules 6–8 duplicated verbatim in todo §9. Delete after verification |
| `playability.md` | 75 L | Self-marked ARCHIVED (rev 7). Friction map, retention loops, emotional design, balance, accessibility, KPIs | KPI table duplicated in todo §12. Accessibility rules → client tasks; friction items → social/client tasks. Delete after verification |
| `mafia_persons.md` | 139 L | Persona portrait cards: art direction, style core, workflow, tooling (`personas/build.php`) | Asset-pipeline doc. Migrates to `09-media/02-personas.md`; `personas/` folder stays as data/tooling |
| `lang/<locale>/` | data | ui.json + bot_players.json for ru/en/zh/es + manifest | Data. Stays untouched (i18n tasks reference it) |
| `personas/<setting>/` | data+tooling | 3 settings × 21 prompt files, build.php, index.json, gallery.html | Data/tooling. Stays; referenced by media tasks |

## A-02. Duplicates found

| # | Content | Copies | Canonical target |
|---|---|---|---|
| D1 | Design rules "glanceability / private-rich-public-calm / recognizable-over-original" (rules 6–8) | todo §9 + ui-patterns "Derived UI rules" (verbatim) | F-05/F-06 + client tasks (one place) |
| D2 | Design rules 1–5 (no single point of waiting, dispute artifacts, boredom budget, growth loops, one surface of truth) | competitive-analysis §4 + todo §9 | F-05 terminology/invariants area — single copy in foundation |
| D3 | Joining refusal rules (lobby-only/capacity/frozen/single-game) | todo §3 + interface-ux §3 | G-01 Room lifecycle (JoinGuard) only |
| D4 | Pause/resume spec | RUN-6 + interface-ux §11 | G-13 recovery/pause task only |
| D5 | Ghost chat spec | competitive P0 #1 + interface-ux §11 + ROOM-12 | G-09 Ghost (API) + feed rendering note |
| D6 | Ready-skip spec | competitive P0 #2 + interface-ux §11 + RUN-5 | G-06/G-07 phase engine |
| D7 | Vote matrix | DISC-4 + interface-ux §11/§9 | G-07 voting + end-game screen task |
| D8 | Playability KPI table | playability §6 + todo §12 | index DoD/KPI section (single copy) |
| D9 | WebApp auth/state/actions contract | WEB-1..3 + interface-ux §13 | API-03/API-07/API-08 + TG webapp client task |

## A-03. Contradictions & drift found

| # | Contradiction | Resolution owner |
|---|---|---|
| C1 | **Notes storage**: interface-ux §11 says notes live "in the snapshot's private section"; §15.3 + ROOM-20 say notes live OUTSIDE the snapshot in a per-user overlay store with own `notesRev`. The rev-4 text was never updated after ROOM-20. | G-10 notes task adopts the ROOM-20 model (outside snapshot) — later revision wins |
| C2 | **State machine granularity**: CORE-1 shows `lobby→night→discussion→voting→end`; draft plan §5 requires `LOBBY→DEAL→NIGHT→MORNING→DISCUSSION→VOTING→RESOLUTION→WIN_CHECK→NIGHT/END`. roles.json implies finer phases (sniper day shot triggers immediate win-check). | F-03 fixes canonical machine; must support mid-phase terminal transitions |
| C3 | **WebApp philosophy**: current docs = "progressive enhancement, third presenter, keyboard parity mandatory"; target = full equal API client. Keyboard parity remains a Telegram-surface rule only. | API-14/TG-08 reframe; parity rule scoped to keyboard surface in TG tasks |
| C4 | **Identity model**: current docs know only `user_id`/`bot_id` (Telegram-bound); target introduces Principal{userId, clientId, scopes} with multiple auth mechanisms. Net-new capability, not a conflict — recorded as a gap. | API-03 |
| C5 | **Stale cross-references**: mafia_persons.md header cites "todo.mafia.md (rev 4)"; ui-patterns adoption table cites interface-ux §numbers that drifted (e.g. ping = §12.6 not §12.7). | Irrelevant after migration; matrix maps by content, not by section number where drifted |
| C6 | **Voting visibility ownership**: secret-ballot flag exists (CORE-8, GRP-5) but who decides visibility is implicit (UI today); target mandates server-side enforcement. | G-07 voting task states server-side rule explicitly |
| C7 | Draft plan internal mismatch: §1 tree lists 12 API files vs §63 lists 17 API tasks; game tree omits ghost/notes/templates; roles split across two trees. Reconciled registry recorded in `../index.md`. | This refactor (done) |

## Registry reconciliation summary (deviations from draft §63)

- API: kept 1:1 (API-01..17); merged scopes into auth file? No — kept separate files per
  task; added nothing. Files renamed to match task IDs.
- Game: templates promoted to its own task (G-02, from body §30 which §63 omitted);
  ghost/notes are game-domain tasks (per draft body §34/35); roles+presets moved to
  `03-roles/` per §1 tree.
- Client: push moved to mobile; reconnect+offline merged; optimistic UI own task;
  game-card/feed/end-game are presentation tasks fed by projections.
- Mobile: iOS/Android generated-client tasks explicit; offline folded into sync.
- Delivery: L-01..L-07 collapsed into one cleanup checklist file (they are meta-steps).
