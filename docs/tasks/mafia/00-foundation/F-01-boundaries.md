# F-01 — Service Boundaries & Architectural Firewall

Status: TODO
Depends: —

## Goal
Fix, once and permanently, what Mafia Game Service owns vs what the Telegram platform owns.
Mafia must not import Telegram platform classes in its domain/application layer; the Telegram
adapter may import the Mafia API client. This is the mandatory firewall that later allows
WebApp/iOS/Android to be equal clients.

## Mafia Game Service owns
rooms · players · seats · roles · phases · actions · votes · deaths · win conditions ·
snapshots · statistics · progression · moderation state · game events.

## Telegram Platform owns
Telegram Bot API · webhook/update transport · callback queries · message sending ·
keyboards · Telegram auth (initData) · Telegram file_ids · rate limiting · outbound queues ·
group/DM semantics.

## Sources
- todo.mafia.md §1 (product/delivery model — module entry stays Telegram-side)
- todo.mafia.md §2 (current engine↔presenter split — becomes API boundary)
- todo.mafia.md §8 (technical decisions: active truth, sender contract)

## Changes
- New canonical boundary statement (this file) referenced by every other task.
- Explicit list of forbidden imports (arch-test subject later in D-03).
- **Capability vs presentation test** (Revision-2 §29): every feature must be classified as
  domain capability → API capability → per-client presentation. Example: «💉 Лечить» is NOT
  an API — the capability is `night.heal`; `[💉 Лечить]` is a Telegram button; native/WebApp
  buttons render the same capability. No client is privileged: Telegram may not have
  gameplay capabilities absent from the canonical API.

## Acceptance
- [ ] Boundary table complete and unambiguous (every entity assigned to exactly one side)
- [ ] Forbidden-imports list written (domain must not touch TgSender/TgBotSetup/callback DTOs)
- [ ] Capability-vs-presentation classification rule stated with worked examples
- [ ] Cross-checked against migration-matrix rows for todo §1–§2

Next: F-02
