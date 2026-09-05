# G-01 — Room Entity, Lifecycle & Join Guard

Status: TODO
Depends: F-02, API-02

## Goal
Room as lobby entity independent of any chat. Lifecycle: `lobby → running → finished|cancelled`
(joining in lobby only). Server-enforced join guard with canonical error codes.

## Properties
kind (`group|interface|mixed`) · visibility (`private|public`) · status · capacity
(min 5–max 15) · role_config (checked roles; mandatory locked ON) · locale · host.

## Visibility matrix (single copy)
DM-created ⇒ public by default. Group-created ⇒ private; public toggle allowed ONLY while
the source Telegram group itself is public (then remote interface seats join via ordinary
mechanics, speaking through the host relay). Mixed = group room that gained interface players.

## Join guard rules
lobby-only · capacity · not frozen · one active game per user · not banned (S-04) ·
rating gate when set (P-03) — refusals as API error codes, adapters localize.

## Admin operations
kick (confirm flow client-side), end-early (force-end), replace-leaver toggle.

## Sources
- todo.mafia.md §3, ROOM-1..2, GRP-7; interface-ux.md §3–§4, §7
- Draft body §29 (GAME-01)

## Acceptance
- [ ] Entity fields + state transitions mapped to F-02/F-03
- [ ] Join refusal → error-code table complete (API-04)
- [ ] Telegram chat_id stored as integration metadata ONLY, never domain identity
