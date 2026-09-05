# M-07 — Release Compatibility Gate

Status: TODO
Depends: M-01, M-02, M-03, M-04, M-05, M-06

## Goal
Verify the mobile DoD gate (index.md) end-to-end: no Telegram-specific required fields ·
no file_ids/callback semantics/message-ID-as-game-ID in any contract consumed by mobile ·
auth independent · Swift/Kotlin clients build from current spec · sync/push/reconnect
documented · version compatibility guaranteed (API-13 policy enforced).

## Acceptance
- [ ] Checklist executed against real generated clients; gaps filed as API tasks
