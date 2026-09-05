# API-04 — Error Contract

Status: TODO
Depends: API-01

## Goal
No Telegram toast strings inside the API. Canonical typed error envelope; adapters localize.

## Shape
```json
{ "error": { "code": "ACTION_STALE", "messageKey": "errors.stale_action", "message": "...", "details": {}, "retryable": false } }
```
`code` = stable machine contract · `messageKey` = localization key into CLIENT lang packs
(reuses existing `ui.json` namespaces) · `message` = debug default (English), never parsed
by clients.

## Codes (starting set)
AUTH_REQUIRED · FORBIDDEN · NOT_FOUND · ROOM_FULL · GAME_STARTED · ALREADY_IN_GAME ·
INVALID_ACTION · INVALID_TARGET · ACTION_STALE · DUPLICATE_ACTION · PHASE_NOT_ACTIVE ·
GAME_PAUSED · RATE_LIMITED · CONFLICT · IDEMPOTENCY_CONFLICT (+ ROOM_ROLES_INVALID,
JOIN_FROZEN, JOIN_LOW_RATING, BOTS_LIMIT_REACHED from legacy toasts).

Registry must cover: validation · authorization · stale · conflict · idempotency errors ·
rate limits — each with HTTP mapping and retryable flag. Legacy toast keys become
adapter-localized renderings of codes via `messageKey` — mapping table in TG-04.

## Sources
- Draft body §12; interface-ux.md §3 (join refusal toasts), §10 (stale/double toasts)

## Acceptance
- [ ] Full code list with HTTP status + retryable flag + details schema per code
- [ ] Rule: backend never depends on client language

Next: API-08 uses codes for ingress rejections
