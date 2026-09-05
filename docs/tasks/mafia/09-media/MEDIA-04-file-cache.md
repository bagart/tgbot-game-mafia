# MEDIA-04 — Telegram File Cache

Status: TODO
Depends: MEDIA-01, MEDIA-02

## Goal
FileIdCache: asset path hash → cached Telegram `file_id` per bot; upload once per bot,
re-send by id. Applies to event art AND persona cards (keyed by filename hash). Missing cache
⇒ upload path; missing asset ⇒ emoji fallback (never blocks a game).

## Sources
- todo.mafia.md IMG-2; mafia_persons.md §8

## Acceptance
- [ ] Cache key/TTL spec; no file_id ever enters Mafia domain (API returns asset references)
