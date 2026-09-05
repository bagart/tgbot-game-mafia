# TG-05 — Mirroring & Speech Relay

Status: TODO
Depends: TG-02

## Goal
MirrorService stays Telegram-specific: while a game runs, every non-command group message is
copied into each participant's interface feed; starts/stops exactly with the game (mirror flag
lives in snapshot → restarts resume correctly); commands/bot messages/media skipped.

Speech relay both directions: interface-only participants speak via forced-reply capture →
attributed post to the group + all feeds (public mixed rooms); per-user speech cooldown with
explanatory rejection.

## Sources
- todo.mafia.md §2, ROOM-8..9; interface-ux.md §5–§6
- Draft body §40

## Acceptance
- [ ] Zero sends when mirror flag off (test)
- [ ] Cooldown budget enforced; batched fan-out via outbound queue
