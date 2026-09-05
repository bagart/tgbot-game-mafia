# API-13 — Versioning Policy

Status: TODO
Depends: API-01

## Goal
`/v1` is the PUBLIC contract version (not Laravel's, not the implementation's). Breaking
change ⇒ `/v2`; additive changes stay in v1. Never silently change enum semantics, field
meaning, action behavior, or privacy rules. Mobile clients may live months on an old version:
additive server changes must keep old clients working (success criterion #10).

## Acceptance
- [ ] Breaking vs non-breaking checklist (consumed by API-17 CI gate)
- [ ] Deprecation policy: header/field marking, docs requirement, sunset rule
- [ ] API changelog maintained as part of the generated artifact release
