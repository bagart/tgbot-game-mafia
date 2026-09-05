# F-02 — Domain Model & Canonical Entities

Status: TODO
Depends: F-01

## Goal
Define the canonical entity set and separate Identity / GameState / PrivateState /
PublicState / ClientProjection. Establish that `GameSnapshot` is internal and NEVER leaves
the service; clients receive projections only.

## Canonical entities (starting set, Revision-2 amended)
Account · Identity (provider-linked) · DeviceSession · Profile · Room · RoomMember · Seat ·
Game · Ruleset {id, version} · Role · Phase · Action · Vote · GameEvent · Snapshot · Result ·
GameReplay · Rating · Achievement · Report.
`User` from the original draft = Account here; Telegram identity is one provider row
(API-03). Capabilities are NOT an entity — a server-computed section of the state envelope
(API-07/API-11).

## Sources
- todo.mafia.md §2 (GameSnapshot readonly DTO, `rev`, private section, mirror flag, seed)
- todo.mafia.md §7 (data model draft: mafia_rooms, mafia_room_members, mafia_games,
  mafia_players, mafia_night_actions, mafia_votes, mafia_profiles, mafia_seasons,
  mafia_ratings, telemetry/bans; Redis keys incl. notes overlay)
- interface-ux.md §10, §13.4 (`rev` / `notesRev` semantics)
- roles.json (Role/Team data shapes)

## Changes
- Entity list with fields, identifiers (UUIDs; no Telegram message IDs as game IDs),
  relationships, and lifecycle ownership.
- Split: identity vs state vs projections; name the three projection types
  (PublicGameView / SelfPlayerView / PrivatePlayerState).

## Acceptance
- [ ] Every registry entity has a definition + ID type + storage owner (Redis/PG/event store)
- [ ] Snapshot declared internal-only; projection rule stated
- [ ] Notes overlay modeled as separate user-scoped store with own revision

Next: F-03
