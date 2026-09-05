# F-06 — Architecture Diagram & Repo Layout

Status: TODO
Depends: F-01, F-02, F-03, F-04

## Goal
Canonical architecture picture + target repository layout for the Mafia service and its
clients, replacing the module-centric layout in legacy todo §2.

## Content
- Diagram: clients (Bot/WebApp/iOS/Android) → Mafia API /v1 (auth·commands·state·events)
  → Game Domain → Persistence/Events (see index.md sketch; expand with event consumers).
- Repo layout decision: where the service lives (new `misc/BAGArt/...` package or host-app
  namespace), where generated clients live, where OpenAPI spec lives (`docs/api/mafia-openapi.yaml`),
  how the Telegram adapter package consumes the generated PHP client.
- Explicitly keep: platform delivery model (path repo, `TgModuleContract` boot) for the
  Telegram adapter ONLY — it must not leak into domain.

## Sources
- todo.mafia.md §1–§2 (module skeleton — migrate the Telegram-side parts here)
- _refactor/migration-plan.md §1 (target state)

## Acceptance
- [ ] Diagram shows all clients equal through one API
- [ ] Repo/namespace plan approved-compatible with project conventions (modules live in
      `misc/BAGArt/<name>-module/`, consumed via path repo)
- [ ] Forbidden-dependency arrows visible on diagram (domain ↛ platform)

Next: Phase API (API-01)
