# Mafia — Documentation & Architecture Refactoring Plan

> Working charter for the Mafia docs refactor. Temporary artifact: lives in `_refactor/`
> and is deleted at the L-phase cleanup gate. English per project language rules
> (translated from the original Russian plan).

Status: ACTIVE (governs the whole refactor)

## 0. Goal

Replace the current scattered Mafia documentation set with a compact system of small,
sequentially executable tasks under `..`.

Problems being fixed:

1. Several documents partially describe the same functionality.
2. UX, gameplay, architecture and implementation are mixed together.
3. A giant todo file cannot be executed comfortably in parts.
4. Iterations produced appendices / rev additions / "archived rationale" layers.
5. The Telegram UI is effectively the center of the current architecture.
6. No real stable external API exists yet.
7. The WebApp is treated as another presenter, not as a full external client.
8. A future iOS/Android client would require a separate contract.
9. Telegram bot platform and the Mafia game engine are too coupled.
10. No guarantee that the Telegram client and a future mobile client share business logic.

## 1. Target state

```
Mafia Game Service
        │
        ├── OpenAPI contract (canonical)
        ├── Telegram Bot Client
        ├── Telegram WebApp Client
        ├── iOS Client
        ├── Android Client
        └── future clients
```

- Telegram Platform = transport/integration layer only.
- Mafia = standalone game product/service.
- OpenAPI = canonical external contract; implementation is never the source of truth.
- Business rules belong to neither the Telegram bot, nor the WebApp, nor mobile clients.
- One game engine, one API contract, many equal clients.

## 2. Documentation rules

After the refactor there must be no: `ui-patterns.md`, `competitive-analysis.md`,
`playability.md`, `mafia_persons.md`, `interface-ux.md`, a giant `todo.mafia.md`,
rev appendices, "additions", duplicated UX specifications.

Every piece of old content is either:

- migrated into canonical architecture/spec tasks;
- turned into a separate implementation task;
- turned into data/config (`roles.json`, lang packs, personas stay data);
- deleted as obsolete/rejected.

Old documents are deleted only at Phase L (cleanup), after their content has landed.

`index.md` contains only: architecture model, dependency order, task list with statuses,
definition-of-done links, working rules. No detailed specifications there.

## 3. Task model

One task = one focused implementation step (~50–200 lines of spec). A task answers:
what to do, why, which files, which invariants, API/OpenAPI impact, tests,
Definition of Done.

Task format:

```md
# <ID> — <Title>
Status: TODO | DONE | WIP
Depends: [IDs]
Goal: ...
Changes: ...
OpenAPI: ... (if applicable)
Tests: ...
Acceptance:
- [ ] ...
Done when: ...
Next: <ID>
```

Context compaction rule: after finishing a task — mark DONE, update index; the next
session starts from `index.md` + the current task + directly required sources only.
Never re-read the whole doc tree. Tasks therefore carry explicit `Sources:` pointers.

## 4. Phases and execution order

A (inventory) → F (foundation) → API (contract) → G (game) → TG (telegram) →
C (generic client) → M (mobile) → S (social) → P (progression) → MEDIA → OPS → L (cleanup).

Full task registry lives in [`../index.md`](../index.md) (single source of task IDs/statuses).
The reconciled registry deviates slightly from the original draft: §63's API-01..17 are kept
1:1; game roles/presets moved into `03-roles/`; some tiny tasks merged per the target tree
in draft §1 (merges are recorded in `inventory.md`).

## 5. Definition of Done gates

### OpenAPI complete when
every public endpoint in spec · every request/response has schema · every error has a
canonical code · auth/scopes/idempotency/pagination/revision/projections documented ·
examples exist · Swagger UI renders · generated PHP client builds · contract tests pass ·
breaking-change check passes.

### Telegram client migrated when
bot has zero game-domain implementation · uses the generated Mafia API client · all actions
and state go through the API · errors mapped from canonical codes · Telegram-specific
rendering isolated in presenters · platform queues outside the game engine · bot restart
never interrupts a game.

### Mobile-ready API when
no Telegram-specific required fields/file_ids/callback semantics/message-ID-as-game-ID ·
auth independent of Telegram · Swift/Kotlin clients generatable · sync + push + reconnect
documented · version compatibility guaranteed.

## 6. Main development rule (§70 of the draft)

New functionality receives, in order: domain concept → API operation → OpenAPI schema →
contract test → client behavior → Telegram presentation → WebApp/mobile presentation.

Forbidden: Telegram button → handler → private Mafia logic → later attempt at an API.
Correct: game capability → API contract → implementation → clients.

## 7. Migration workflow (this refactor)

1. **Phase A** (this session): inventory all existing docs, find duplicates and
   contradictions, build the migration matrix, fix the deletion list
   (see `inventory.md`, `migration-matrix.md`).
2. Generate the new folder tree: `index.md` + small task files (stubs carrying absorbed
   Done-when criteria and precise source pointers).
3. Execute tasks one per session in index order; migrate content into the executed task.
4. **Phase L**: run `php _refactor/verify.php` until green (link/deps/status/
   coverage gate), delete legacy docs listed in `migration-matrix.md` §A-05,
   final lint.

Legacy docs stay frozen during migration: do not edit them; new decisions land only in
new tasks.
