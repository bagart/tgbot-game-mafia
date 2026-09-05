# F-05 — Terminology & Design Rules

Status: TODO
Depends: F-01

## Goal
Single copy of the glossary and the 14 design rules (dedupe D1/D2 from inventory.md:
rules currently exist verbatim in todo §9 + ui-patterns + competitive-analysis §4).
Terminology aligns with `../../../../../../../docs/glossary.md` conventions of the platform.

## Content
- Terms: Room, Game, Phase, Seat, Action, Vote, Snapshot, Projection, Principal, Rev,
  NotesRev, Mirror, Relay, Ghost, Persona, Setting (era), Ruleset/Preset version.
- The 14 design rules (no single point of waiting; disputes resolvable from artifacts;
  boredom budget ≤ 20 s; user-initiated growth loops; one surface of truth per player;
  glanceability; private layer rich/public calm; recognizable over original; never
  color-only signals; dead ends forbidden; fail closed/boot isolated; keyboard parity as a
  TELEGRAM-surface rule only; graceful degradation; reportable & governable).

## Sources
- todo.mafia.md §9 (canonical list)
- ui-patterns.md "Derived UI rules", competitive-analysis.md §4 (duplicates to absorb)
- interface-ux.md §0 (UX principles — merge non-duplicated ones)

## Acceptance
- [ ] Glossary terms match entity names from F-02
- [ ] Rule 12 rescoped: parity applies to Telegram keyboard surface, not to WebApp/mobile
- [ ] This file becomes the ONLY location of the design rules

Next: F-06
