# API-14 — OpenAPI Generation Pipeline (Scramble)

Status: TODO
Depends: API-01..API-13, API-18

## Goal
THE canonical contract is the Scramble-generated OpenAPI artifact produced from the Laravel
implementation (controllers · form requests · DTOs · annotations). No hand-written YAML, no
second documentation surface, no per-client contracts. Scalar publishes the interactive
reference from the same artifact.

## Pipeline
Laravel code → Scramble → OpenAPI artifact (committed snapshot for CI)
→ Scalar docs UI → contract tests (API-16) → generated clients where useful (API-15).

## Structure
components/schemas: Account · Room · Game · StateEnvelope (public/self/private/capabilities)
· Action (polymorphic + discriminator) · Event · Error (code+messageKey+details) · Pagination
(+ role metadata, ghost, notes, ruleset identity). Every operation carries examples.

## Sources
- `_refactor/architecture-review.md` PASS 2/stack decisions (supersedes draft §19–20 wording)
- API-18 decision record; all API-01..API-13 outputs feed schemas

## Acceptance
- [ ] Every endpoint from API-02 present with request/response/error schemas
- [ ] Auth schemes, scopes, idempotency header, pagination, rev documented
- [ ] Polymorphic actions render with discriminator; examples validate against own schemas
- [ ] Generated artifact renders in Scalar UI; committed snapshot diff-stable
