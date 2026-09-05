# API-18 — Scramble/Scalar Stack Spike (BLOCKING for API-14)

Status: TODO
Depends: API-01
Blocks: API-14

## Goal
Half-day spike proving `dedoc/scramble` can express the canonical contract BEFORE any schema
work. Scalar (`scalar/laravel`) = documentation UI only, never a source of truth.

## Spike must answer
- Polymorphic Action schema: `oneOf` + `discriminator` over action-type DTOs (R-04 catalog) —
  automatic, or via explicit schema classes/annotations?
- State envelope representation (API-07): nested projections, capabilities array, enums.
- Error envelope as reusable component + per-operation error lists.
- Security schemes (bearer/session), idempotency header parameter, pagination components.
- Custom OpenAPI extensions & examples from attributes; spec serialization to a committed
  artifact for CI diffing.
- Limits/annoyances log → decision record.

## Rule
If automatic generation is insufficient for a construct: use explicit DTO/schema annotations
or custom Scramble extensions living BESIDE the code they describe. NEVER introduce a
hand-written YAML as a second source of truth.

## Sources
- `_refactor/architecture-review.md` PASS 2 (binding stack decisions)
- Revision-2 meta-prompt §4–5, §21

## Acceptance
- [ ] Spike branch renders a sample polymorphic action + error + envelope in generated spec
- [ ] Decision record: what is automatic / annotated / extended
- [ ] Snapshot mechanism proven (spec artifact committed, diffable)

Next: API-14
