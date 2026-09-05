# G-02 — Room Templates & Preset Import

Status: TODO
Depends: G-01, R-03

## Goal
Templates («Классика», «Молния», «Турнирный») as DATA, not UI: `GET /v1/rooms/templates`,
`POST /v1/rooms {template, overrides}`. One tap applies timing/config presets; still editable
afterwards. Same templates for Telegram, WebApp, mobile. Future: preset share-codes
(role-config serialized short code, import in wizard).

## Sources
- todo.mafia.md GRP-10, ADV-3; playability.md friction rows (templates); draft body §30–31

## Acceptance
- [ ] Template definitions in data files with versioning
- [ ] Override validation reuses R-02 constraint checks
