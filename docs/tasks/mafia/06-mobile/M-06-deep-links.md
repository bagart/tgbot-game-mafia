# M-06 — Deep Links (platform-neutral)

Status: TODO
Depends: S-03

## Goal
Canonical deep-link resources: room · game · profile · invite. Telegram deep links remain
adapter-specific (`?start=mafia_room_<id>`); mobile defines its own scheme mapping to the same
canonical resources. Links must not depend on Telegram.

## Sources
- Draft body §50; todo.mafia.md ROOM-5/11

## Acceptance
- [ ] Resource grammar table shared across platforms; each client maps locally
