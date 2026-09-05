# API-17 — Breaking-change CI Gate

Status: TODO
Depends: API-15, API-16

## Goal
CI fails on breaking API changes. Because the spec is GENERATED (API-14), the gate works on
the committed artifact snapshot: diff generated spec vs baseline branch; classify changes via
the API-13 checklist; fail on breaking classes.

## Checks
spec snapshot diff vs main · operation-ID uniqueness · schema lint · undocumented errors ·
examples validate · generated client compiles (API-15) · Telegram adapter & WebApp clients
remain compatible (regenerate + compile in CI).

## Sources
- Draft body §23; _refactor/migration-plan.md §5 gate list
- Platform CI conventions (SHA-pinned workflows; local validation via tools/baseline/yaml-lint.php)

## Acceptance
- [ ] Gate runs in CI on every PR touching the spec or API code
- [ ] Red example recorded (deliberately broken spec fails)
