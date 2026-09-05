# API-15 — Generated Clients

Status: TODO
Depends: API-14

## Goal
Generate clients from the Scramble-produced OpenAPI artifact (API-14) — never hand-written
DTOs. PHP client (`MafiaApiClient`) for the Telegram adapter; Swift/Kotlin/TypeScript
generatable for mobile/WebApp tracks. Any API change happens in code, regenerates the spec,
then propagates to clients via regeneration + CI compatibility checks.

## Sources
- Draft body §21–22; _refactor/migration-plan.md §5 gates
- Platform convention: strict contracts only, no duck typing across library boundaries

## Acceptance
- [ ] Generator toolchain chosen + build wired (composer package or scripts)
- [ ] PHP client builds in CI; Telegram adapter is its only consumer (TG-01)
- [ ] Swift/Kotlin generation proven at least once (M-02/M-03 consume)
