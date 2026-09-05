# API-16 — Contract Tests (incl. Security)

Status: TODO
Depends: API-14

## Goal
OpenAPI request → real API → validate response against spec. Security tests must PROVE
privacy invariants (F-04 #10, API-11).

## Scenario list
auth · room create/join/start · state · action · stale action · duplicate action · private
projection · forbidden information. Security: player A never receives player B's private
state · dead-player info does not leak · detective results owner-scoped · notes owner-scoped ·
ghost messages audience-filtered · admin actions need scope · room visibility enforced
server-side · initData validated · mobile tokens cannot access other users · API never trusts
client-supplied role/team/winner.

## Sources
- Draft body §24, §57; F-04 invariant mapping

## Acceptance
- [ ] Each scenario a named test linked to an OpenAPI operation
- [ ] Cross-user leak matrix implemented (per projection field)
