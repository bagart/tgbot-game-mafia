# D-03 — Testing Strategy

Status: TODO
Depends: API-16

## Goal
Single testing strategy: unit (pure core, role interactions, resolution/win order, set
builder, i18n escaping/plurals) · feature (simulated games with deterministic bots + fake
clock/sender; pause/restart roundtrips; mirror boundaries) · integration (host enablement,
settings, migrations, command interception) · contract (API-16 + presenter event contract +
lang-pack key parity in CI) · arch tests (fairness firewall, append-only facts, no platform
leakage into domain) · load/soak (OPS-06/07) · telemetry feature tests.

## Acceptance
- [ ] Test-type → task mapping table complete; parity test wired for all locales present
