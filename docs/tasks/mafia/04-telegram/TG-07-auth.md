# TG-07 — Telegram Auth Bootstrap (initData/session)

Status: TODO
Depends: API-03

## Goal
Adapter-side half of Telegram auth: obtain initData in WebView, exchange at the API for a
canonical Principal/session; attach session to all WebApp requests. Multi-bot: resolve bot
token by the room's `bot_id` from DB (never single .env token). Capability detection: missing
`window.Telegram?.WebApp` or empty initData ⇒ keyboard-fallback notice, chat flow continues.

## Sources
- todo.mafia.md WEB-1/WEB-5 fallback; interface-ux.md §13.2–13.3
- API-03 owns validation/session issuance — this task only wires the client side

## Acceptance
- [ ] Launch points: web_app buttons paired with callback-equivalent rows · MenuButtonWebApp ·
      direct `?startapp=mafia_room_<id>`
