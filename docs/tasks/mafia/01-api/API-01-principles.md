# API-01 — API Principles

Status: TODO
Depends: F-01

## Goal
Declare the API a product API, not an HTTP wrapper over the Telegram bot. First version
lives under `/api/v1/`.

## Principles (finalize wording)
resource-oriented · versioned · stateless HTTP · explicit commands/actions · stable IDs ·
cursor pagination · idempotency keys on mutations · optimistic concurrency (rev) · typed
errors · public/private projections · deterministic state revision · backward compatibility.

## Sources
- _refactor/migration-plan.md §1/§6 (draft §7 + §70 development order rule)
- F-01 boundary statement

## Acceptance
- [ ] Principle list ratified; each principle linked to the task that implements it
- [ ] `/api/v1/` prefix decision recorded; no Telegram terms in resource naming

Next: API-02
