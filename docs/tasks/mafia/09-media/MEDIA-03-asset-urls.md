# MEDIA-03 — Client Asset URLs

Status: TODO
Depends: MEDIA-02

## Goal
API returns `settingId` / `personaId` / asset REFERENCES only. Telegram resolves references →
file_id (MEDIA-04); mobile/WebApp resolve → CDN/image URLs. No Telegram file_id in Mafia
domain (mobile DoD gate).

## Acceptance
- [ ] Reference schema in OpenAPI; resolution strategy per client documented
