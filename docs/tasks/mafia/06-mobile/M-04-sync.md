# M-04 — Mobile State Sync Usage

Status: TODO
Depends: API-07

## Goal
Mobile consumes the canonical sync mechanism (rev long-poll + fallback), C-01 state model,
C-07 optimistic rules, C-08 reconnect. No mobile-specific sync protocol — if this task needs
a new endpoint, the change starts in OpenAPI instead.

## Acceptance
- [ ] Battery/lifecycle guidance (background/foreground transitions, hold-window usage)
