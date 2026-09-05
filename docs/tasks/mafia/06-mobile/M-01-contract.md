# M-01 — Mobile Auth & Session Contract

Status: TODO
Depends: API-03

## Goal
Telegram-independent auth for iOS/Android: registration/login → access token (+refresh,
rotation) → Principal with user scopes. Zero Telegram-required fields anywhere in the mobile
contract (DoD gate in index.md). Service-to-service token documented for future first-party
integrations.

## Sources
- API-03; draft body §28; _refactor/migration-plan.md §5 mobile gate

## Acceptance
- [ ] Token lifecycle (issue/refresh/revoke) + storage guidance per platform
