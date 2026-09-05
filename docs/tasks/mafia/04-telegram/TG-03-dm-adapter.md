# TG-03 — DM Adapter & Onboarding Surfaces

Status: TODO
Depends: TG-01

## Goal
Private-chat surface: main menu (Играть/Комнаты/Статистика/Настройки/Как играть), room list
+ card, creation wizard keyboard chain (forced-reply steps, cancellable; mandatory roles
locked), deep-link landing (`?start=mafia_room_<id>`, `?startapp=`), «вернуться в игру»,
night action menus, training mode entry.

## Onboarding
`/start` router with ≤2-taps-to-table rule · first-run tutorial once ever · `/rules`
paginated wiki · coach hints first 3 games · `/roles` encyclopedia from safe role metadata
(R-01) · bot profile packaging (`setMyCommands`, localized description/about).

## Sources
- interface-ux.md §1–§4, §6; todo.mafia.md ONB-1..3, ROOM-3..5/14, I18N-5/6
- i18n infra: LangPack loader ({placeholder} interpolation + HTML escaping, CLDR plurals,
  buttons ≤24 chars) + LocaleResolver chain room→chat→bot→en — spec here, data in `lang/`

## Acceptance
- [ ] Every entry point lands on a live API-backed surface (no dead menus)
- [ ] Wizard produces identical role_config via both keyboard and WebApp form
