# API-05 — Action Idempotency

Status: TODO
Depends: API-02

## Goal
All gameplay writes require an external `Idempotency-Key: UUID` header IN ADDITION to the
internal dedupe key. Two layers:
1. **External key** (client-generated UUID): server caches the first response; a replay —
   even after server commit, timeout, or retry storm — returns the ORIGINAL logical result,
   never a second effect.
2. **Internal semantic key** `(accountId, gameId, phaseNumber, actionType)` (legacy RUN-2
   heritage): catches double-tap/duplicate intents regardless of headers.

## Analysis items (Revision-2 §12)
storage + TTL · replay response caching · concurrent duplicate requests (same key in flight) ·
Telegram double callback · mobile network retry · timeout-after-server-commit.

## Sources
- todo.mafia.md RUN-2; interface-ux.md §10 (`errors.double_action_toast`)
- Revision-2 meta-prompt §12

## Acceptance
- [ ] External-key scope + TTL + storage defined (Redis-first, per platform state rules)
- [ ] Concurrent duplicate: single-flight behavior specified (wait-for-first vs reject)
- [ ] Replay response contract (same body/status as first response)
- [ ] Conflict case: same key different payload ⇒ `IDEMPOTENCY_CONFLICT` 409/422 (API-04)

Next: API-08
