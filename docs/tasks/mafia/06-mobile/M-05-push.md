# M-05 — Push Notifications (wakeup-only)

Status: TODO
Depends: API-09

## Goal
Push categories: phase started · your turn · game resumed · game finished · room invitation.
Payload = event reference + gameId ONLY — never private game state. Client then fetches state
via API (rev long-poll). APNS/FCM wiring specs; user-level opt-out preferences.

## Sources
- Draft body §16/§49; API-10 realtime strategy

## Acceptance
- [ ] Payload schema reviewed against projection rules (no leaks)
