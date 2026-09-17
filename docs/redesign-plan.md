# Mafia Game — Remaining Redesign Work

Completed redesign decisions are retained in [SDD-mafia-game.md](SDD-mafia-game.md). This plan contains only outstanding work or acceptance criteria whose completion remains unverified.

## Quickplay Matchmaking

- [ ] Replace the onboarding quickplay placeholder with a bot-scoped matchmaking flow.
- [ ] Queue players by waiting time; create and start a room when at least five players are available.
- [ ] Remove and notify players when fewer than five are available after 60 seconds.
- [ ] Verify concurrent joins, repeated requests, cancellation and isolation between bots.

The original design proposes a Redis sorted set per bot. `WelcomeCard` still routes quickplay to the `onbsoon` placeholder.

## Rematch Acceptance

- [ ] Verify the existing `GameCoordinator::rematch()` and `again` callback against the intended finished-game flow; complete any missing behavior and regression coverage.

The original plan called rematch incomplete. A coordinator implementation now exists, so this is an acceptance check, not a request to implement a duplicate path.

## Mini App Acceptance

The status summary reports Mini App components, while the redesign plan did not record acceptance. Keep these checks open until behavior and tests establish completion.

- [ ] Verify live game-board state: seats, phases and viewer-appropriate role visibility.
- [ ] Verify private night-action selection and action authorization.
- [ ] Verify anonymous vote submission and confirmation.
- [ ] Verify read-only spectator access with the originally required 30-second delay.

## Verification

- [ ] Run the affected module tests and confirm Telegram/Mini App integration for each remaining flow before closing its task.
