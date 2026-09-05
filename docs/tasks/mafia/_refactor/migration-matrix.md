# Mafia Docs — Migration Matrix (A-04) & Deletion List (A-05)

Status: COMPLETE (2026-08-24) · ID-canonicalized + machine-verified 2026-08-26
(`php _refactor/verify.php`) · Verification gate for Phase L (`99-delivery/D-05-cleanup.md`).

Rule: every content block below must land in exactly ONE new task before its source file
is deleted. `→` = target task file. Target column uses canonical registry IDs from
`../index.md`; Content column keeps legacy IDs from the source docs. Status column:
`todo` until the target task is DONE.

## 1. todo.mafia.md (rev 9–10)

> rev 10 (2026-08-26 platform-reality pass: package/repo naming, boot registration
> convention, host-scheduled `mafia:sweep` via `routes/console.php`, lang packs +
> `roles.json` already packaged) routes to the same targets: wiring → TG-01,
> sweep/recovery → G-13; ADV-6 closed by it (no target needed).

| Section | Content | → Target | Status |
|---|---|---|---|
| §0 Doc Map, string-key namespaces | doc roles, `extras*.*` key blocks | index.md (done, condensed); key namespaces → TG-04 + C-* rendering tasks | todo |
| §1 Product, two modes, mixed rooms, delivery model, MafiaModule snippet | product scope; module entry stays Telegram-side | F-01 (scope boundary); TG-01 (module wiring incl. boot registration + packaging, rev 10) | todo |
| §2 Architecture: GameCore/presenters/MirrorService/relay/WebApp/notes overlay | engine↔presenters split becomes API boundary | F-06 diagram; API-09 events; TG-05 mirror; G-10 notes | todo |
| §2 Repository structure | module repo layout | OPS-01 (service-boundary note); layout stays a module implementation detail — F-01 appendix line only | todo |
| §3 Room system + visibility matrix + join rules | room entity/visibility/join guard | G-01 room (+ G-02 templates) | todo |
| §4 Roles summary → roles.json | pointer | R-01 catalog | todo |
| §5.1 PLAT-1..5 | package skeleton, module entry, enablement, provider, settings | TG-01 (skeleton, entry, enablement, provider, settings — platform-side wiring) | todo |
| §5.2 CORE-1..9, CORE-10 | state machine, snapshot DTO, role registry, set builder, night resolver, win checker, seeded RNG, voting logic, view firewall, 16 roles | F-03; G-13 snapshots; R-01..R-03; G-05 night; G-11 win; G-12 fairness; G-07 voting; API-11 projections firewall; R-05 bot fairness | todo |
| §5.3 RUN-1..6 | Redis store+locks, atomicity/dedupe/stale, sweep/recovery, manager/events, ready-skip, pause/resume | API-05 idempotency; API-06 revision; G-05/G-07 engines; G-13 recovery + host-scheduled `mafia:sweep` (rev 10); G-06 ready-skip; OPS-02 locks | todo |
| §5.4 GRP-1..12 | /play lobby, DM gate, start guard, night menus, vote board, end screen, host tools, +30s, emergency, templates, seat shuffle, keyboard affordance (GRP-12) | TG-02 group adapter; TG-03 dm adapter; TG-04 presenter (incl. GRP-12 button styles); G-02 templates; G-06 day/emergency; C-* screens | todo |
| §5.5 ROOM-1..21 | rooms persistence/visibility/list/wizard/deep links/interface presenter/callback router/mirror/relay/quickplay/return link/ghost/marks/training/pings/avatar/a11y/log/wills (ROOM-19)/notes store/marks UX | G-01; G-09 ghost; G-08 wills reveal (ROOM-19); G-10 notes; S-01 quickplay; S-02 public rooms; S-03 sharing/deep links; TG-04/TG-05/TG-06; C-* screens; MEDIA-05 avatar surface (P-01 persistence); M-06 deep links | todo |
| §5.6 WEB-1..8 | initData auth, read API long-poll, action pipeline, screens v1, launch/fallback, theme, feel, resilience | API-03 auth; API-07 sync; API-08 actions; TG-08 webapp client (WEB-4..8 presentation); C-08 reconnect/resilience | todo |
| §5.7 BOT-1..7 | nicknames, heuristic brain, persona speaker, fairness firewall, capacity, replace-leaver, personalities | R-05 fairness firewall (+ BOT-7 brain profiles); MEDIA-02 personas; S-01 quickplay bot-fill; G-03 capacity rules; G-01+API-11 replace-leaver inherited view | todo |
| §5.8 I18N-1..7 | langpack loader, locale resolver, parity CI, zh/es, tutorial/rules, coach hints, copy pass | TG-03 (i18n infra: loader/resolver/parity CI); onboarding surfaces → TG-03 + C-*; coach hints → C-03; copy pass → D-04 release gate; data = `lang/` (stays) | todo |
| §5.9 DISC-1..9 | AFK freeze, sleepy badge, profiles/stats, vote matrix, share card, MVP voting, streak, reports/karma, achievements | freeze → G-01 JoinGuard + P-01; sleepy → G-08; stats → P-01/P-02; vote matrix → G-07 + C-05; share card → S-03 + C-05; MVP voting → G-11/C-05; streak → P-01; reports/karma → S-05; achievements → P-05 | todo |
| §5.10 RAT-1..8 | facts log, eligibility, tier, crown formula, projector, seasons/boards, championships, crowns UI | P-03 rating; P-04 seasons; S-02 public rooms (eligibility); C-* crowns rendering | todo |
| §5.11 IMG-1..5 | event PNGs, FileIdCache, art variety, persona integration, reactions | MEDIA-01..MEDIA-05 | todo |
| §5.12 ADV-1..7 | provable fairness (dup of G-12), balance telemetry, preset codes, scheduled rooms, web dashboard, cron capability, spectators | ADV-1 → G-12 commit/reveal; ADV-2 → P-02 balance telemetry + S-05 anomaly queue; ADV-3 → G-02 preset share-codes; ADV-4 → S-06 scheduled rooms; ADV-5 web dashboard → P-02 read-only surfaces (privacy review); ADV-6 CLOSED by rev 10 (host `routes/console.php` schedules `mafia:sweep` — no registrar cron); ADV-7 spectators → API-11 public projection + S-02 | todo |
| §5.13 ONB-1..3 | /start router, role encyclopedia, bot packaging | /start router + deep-link bypass → TG-03 (+ G-01/S-03 targets); role encyclopedia → R-01 metadata consumer rendered by C-*; bot packaging (`setMyCommands`, descriptions) → TG-01 | todo |
| §5.14 MOD-1..4 | reports, input hygiene, owner toolkit/bans, privacy/delete_me | MOD-1 → S-05; MOD-2 → TG-04/C-02 rendering + server-side validation (G-01); MOD-3 bans/owner toolkit → S-04; MOD-4 privacy/`/delete_me` → OPS-09 + P-01 purge | todo |
| §5.15 legacy OPS-1..6 | telemetry funnel, alerting, load/soak, kill-switches, perf CI, release runbook | legacy OPS-1 funnel → OPS-09 telemetry; OPS-2 alerting → OPS-05 observability; OPS-3 load/soak → OPS-06 + OPS-07; OPS-4 kill-switches → D-02 flags; OPS-5 perf budget → OPS-06 nightly; OPS-6 runbook → D-04 | todo |
| §5.16 Rejected list | do-not-build list | index.md rules section (single copy) | done-in-index |
| §6 Waves & milestones W0–W8 | execution order | superseded by index.md phase order | done |
| §7 Data model SQL + Redis keys | schema draft | D-01 migrations (+ G-13 snapshot keys) | todo |
| §8 Technical decisions | active-truth-in-Redis, sender contract, timers, ratings derived, notes scratchpad, privacy, bot fairness, telemetry aggregate-first, kill-switches | distributed: F-01/F-05, OPS-*, G-10, G-12, P-03 | todo |
| §9 Design rules 1–14 | review checklist | F-05 invariants/terminology (single copy; D1/D2 dedupe) | todo |
| §10 Testing strategy | test pyramid incl. arch tests | D-03 testing strategy | todo |
| §11 Risks table | risk/mitigation | 10-platform tasks (each mitigation lands with its feature); matrix keeps list here as source | todo |
| §12 Acceptance criteria + KPIs | functional gates + KPI table | index.md DoD section (dedupe D8) | done-in-index |

## 2. interface-ux.md

| Section | → Target |
|---|---|
| §0 UX principles 1–6 | 05-client/01 (client-state principles) + F-05 |
| §1 Entry points | TG-02/TG-03 adapters; deep links → M-06/S-03 |
| §2 Main menu, §3 Room list/card | TG-03 dm adapter; G-01 room card data |
| §4 Creation wizard | G-01/G-02 (API semantics); keyboard flow → TG-03 |
| §5 Group lifecycle + mirroring rules | TG-02 group adapter; TG-05 mirroring |
| §6 Game card/feed/action screens mockups | C-02 game-card, C-04 feed, C-03 action screens |
| §7 Host tools | G-01 admin ops (kick/end/replace) API + TG-04 presentation |
| §8 Event images assets tree | MEDIA-01 event-art |
| §9 End screen | C-05 end-game |
| §10 State/idempotency/recovery | API-05/API-06/G-13 (contracts move to API phase) |
| §11 Rev-4 additions (ghost, ready-skip, pause, matrix/share, notes, cooldown, tutorial) | G-09; G-06; G-13; C-05; G-10; TG-04; TG-03 onboarding |
| §12 Rev-5 patterns (card v2, inspector, emergency, predictions, training, pings, avatar, wills, event log, reactions) | C-*; G-06 emergency; ROOM items already mapped |
| §13 WebApp presenter (why/auth/state/actions/screens) | API-03/API-07/API-08 + TG-08 webapp client |
| §14 Crowns/tiers UI | P-03/P-04 + C-* rendering |
| §15 Pencil marks palette/lifecycle/storage | G-10 notes (canonical); inline cap rule → C-02 |
| §16 Release polish bar checklist | D-04 release runbook (M2 gate) |

## 3. competitive-analysis.md (ARCHIVED)

Pain-point matrix → covered by mapped features (verify at L). Steal-list P0–P2 → all mapped.
§3 Rejected monetization → index rules (merged with todo §5.16). §4 Design rules 1–5 → F-05.

## 4. ui-patterns.md (ARCHIVED)

Adoption table rows → respective 05-client / G-xx tasks (all adopted already).
Rejected patterns → index rules. Derived rules → F-05 (D1 dedupe).

## 5. playability.md (ARCHIVED)

Friction map → S-01 quickplay, G-06 pacing (+30s/coach), G-02 templates, P-05 MVP,
G-04 shuffle, C-06 a11y, S-06 scheduled. Retention loops → P-05 achievements.
Emotional design → MEDIA-01 variety + C-05. Balance → P-02 statistics/telemetry.
Accessibility rules → C-06. KPIs → index DoD (D8). §7 rejected → index rules.

## 6. mafia_persons.md

Concept/style/sourcing/workflow/tooling/integration/roadmap/status → 09-media/02-personas.md
(single rewrite). `personas/**` folder + `build.php` stay as data/tooling, referenced from there.

## 7. roles.json — STAYS (canonical data)

Referenced by 03-roles/* tasks; future home = module package resources. No content migration;
catalog/constraints/presets specs cite it as the data source.

## A-05. Deletion list (execute ONLY at Phase L, after all targets are DONE)

| File | Action |
|---|---|
| `../todo.mafia.md` | DELETE after every matrix row above is `done` |
| `../interface-ux.md` | DELETE (added by inventory; draft §58 omission fixed) |
| `../ui-patterns.md` | DELETE |
| `../competitive-analysis.md` | DELETE |
| `../playability.md` | DELETE |
| `../mafia_persons.md` | DELETE (superseded by MEDIA-02-personas.md) |
| `misc/BAGArt/tgbot-game-mafia/docs/tasks/mafia/_refactor/` | DELETE whole folder at L-end (incl. verify.php) |
| `roles.json`, `lang/**`, `personas/**` | KEEP (data/assets; later moved into the module package by packaging task) |

Also update then: repo-root `docs/INDEX.md` mafia rows and `AGENTS.md` (+ CLAUDE.md/GEMINI.md
mirrors per sync rule) mafia-module line. Done 2026-08-26 — all four already point at
`../index.md` as the plan hub; at Phase L only the legacy files themselves remain to delete.

## Revision 2 amendment (API-first / Scramble) — 2026-08-24

Binding decisions recorded in `architecture-review.md`; registry deltas:

| Concern (Revision-2 meta-prompt) | Disposition | Target |
|---|---|---|
| Scramble/Scalar stack, code-first contract; hand-written YAML forbidden | AMENDS draft §19–20 and v1 API-14 wording | API-18 spike → API-14 pipeline; API-15/16/17 amended |
| Capability-based state (`availableActions[]`) | NEW | API-07 envelope + API-11 note; F-01 capability-vs-presentation rule |
| Formal state envelope (serverTime, ruleset identity, status) | EXPANDED | API-07 (rewritten) |
| Sync `?since=` deltas | DEFERRED (deviation #1); parameter reserved | API-07 transport ladder |
| Idempotency: external UUID key + semantic key | EXPANDED (two layers) | API-05 (rewritten) |
| Error `code` + `messageKey` localization | EXPANDED | API-04 (+IDEMPOTENCY_CONFLICT, ACTION_STALE naming) |
| Account ← identity providers (telegram/apple/google/future), device sessions | NEW entity set | API-03 (rewritten), F-02 entities |
| Ruleset `{id, version}` pinned per game | CONSOLIDATES scattered version names | R-06 (new); G-12/G-13/R-03 amended |
| GameReplay formal model | NEW (public endpoint deferred — deviation #2) | G-14 (new); G-12 narrowed to RNG mechanics |
| Logical separation first, single deployment v1; no K8s/Kafka/gRPC | NEW guardrail | OPS-01 amendment; stack decisions in index.md |
| No-client-is-privileged rule; «💉 Лечить» ≠ API | NEW principle | F-01; index working rule 3 |

Migration-matrix rows above remain valid; where they cite draft §19–20 ("OpenAPI = source of
truth"), read them through this amendment (generated artifact = the contract).