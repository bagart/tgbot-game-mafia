# TG-08 — WebApp Client v1

Status: TODO
Depends: TG-07, API-07, API-08

## Goal
WebApp = full Mafia API client (not a special presenter). initData → API session (TG-07);
state via long-poll `?rev=`; actions via the one ingress pipeline. Keyboard remains the
canonical fallback for Telegram users — parity is scoped to the keyboard surface.

## Screen set v1
game card · roster · night forms · vote grid (no throttle) · marks editor popover · notes +
seat inspector · ghost panel · end screen + matrix · room wizard as ONE form.
Feel/resilience: themeParams sync, safe-area, haptics on cast/vote/mark, BackButton stack,
skeletons on deferred state, stale banner + auto-reconnect reconciled by rev/notesRev,
closing confirmation on unsent action. XSS: mirrored text sanitized to text nodes + strict CSP.

## Sources
- todo.mafia.md WEB-1..8; interface-ux.md §13 (+ §16.2 checklist)
- Draft body §27

## Acceptance
- [ ] Parity checklist green; game completable without opening the app
- [ ] Forged/replayed initData rejected; cross-user leak test fails closed (API-16)
