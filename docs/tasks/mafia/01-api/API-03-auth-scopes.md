# API-03 — Accounts, Identity Providers & Scopes

Status: TODO
Depends: API-01, F-02

## Goal
Account-based identity (Revision-2 amendment): an `Account` aggregate with linked identity
providers — `telegram` · `apple` · `google` · future. A Telegram user is ONE identity
provider, not the account itself. All auth mechanisms collapse into one canonical Principal
`{accountId, clientId, scopes}`; a Telegram bot token is NOT a game identity (bots use
service credentials with narrow scopes).

## Flows to specify
- Telegram initData exchange → session (per-bot DB token, freshness ≤1h, replay protection,
  short-lived signed session ~30 min sliding) — unchanged heritage.
- Mobile: access token + refresh token + device sessions (issue/rotate/revoke), zero
  Telegram dependency.
- Account linking policy (merge rules, one identity per provider per bot scope).
- Apple/Google sign-in: SCHEMA-READY ONLY (provider column + flow placeholder) — no provider
  implementation until mobile phase justifies it (overengineering guard).

## Scopes (minimum)
`game:read` `game:action` `room:read` `room:write` `profile:read` `profile:write`
`stats:read` `moderation:write`.

## Sources
- todo.mafia.md WEB-1 / interface-ux.md §13.3; draft body §17–18
- Revision-2 meta-prompt §14; `_refactor/architecture-review.md` PASS 2
- Platform convention: tokens in DB (`tg_bots`), never .env

## Acceptance
- [ ] Account/Identity/DeviceSession entity definitions fed back into F-02
- [ ] initData validation spec (multi-bot by room's bot_id)
- [ ] Mobile token lifecycle documented; works without any Telegram artifact
- [ ] Scope matrix per client type (bot service / webapp user / mobile user / admin)

Next: TG-07 consumes this for WebApp bootstrap; M-01 consumes for mobile
