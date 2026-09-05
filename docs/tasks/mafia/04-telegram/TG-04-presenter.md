# TG-04 — Keyboard Presenter & Callback Mapping

Status: TODO
Depends: TG-02, TG-03

## Goal
`TelegramGamePresenter`: takes canonical API projections/events ONLY (never domain objects).
Owns Game Card, keyboards, callback mapping, toasts, photos, Telegram formatting; maps
canonical error codes → localized `ui.json` keys (`errors.*`, `rooms.*`, legacy `extras*.*`
namespaces absorbed).

## Rules
immediate `answerCallbackQuery` for every callback · no dead ends (error explains next step) ·
button colors: confirm=success, kick/end=danger · seat numbers on buttons · live board edits
throttled ≥3 s · keyboard parity rule applies to THIS surface only.

## Sources
- todo.mafia.md ROOM-6/7/11, GRP-12, §0 key namespaces; interface-ux.md §0–§6, §12.1
- Draft body §26

## Acceptance
- [ ] Presenter input types = API projections/events exclusively (typed contract)
- [ ] Error-code→string-key mapping table complete
