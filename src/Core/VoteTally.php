<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core;

/**
 * Day-vote tally. Open ballot: voter => seat (-1 = abstain).
 * Majority eliminates; tie → revote among tied (once per round);
 * second consecutive tie → no elimination.
 */
final class VoteTally
{
    public static function tally(GameSnapshot $snapshot): VoteOutcome
    {
        $counts = [];
        foreach ($snapshot->votes as $target) {
            if ($target < 0) {
                continue;
            }
            $counts[$target] = ($counts[$target] ?? 0) + 1;
        }

        $aliveIds = array_map(fn (SeatState $s) => $s->seat, $snapshot->aliveSeats());
        $candidates = $snapshot->revoteCandidates === []
            ? $aliveIds
            : array_values(array_intersect($snapshot->revoteCandidates, $aliveIds));
        $counts = array_filter($counts, fn ($t) => in_array($t, $candidates, true), ARRAY_FILTER_USE_KEY);

        if ($counts === []) {
            return new VoteOutcome(null, [], 0, count($snapshot->votes));
        }

        arsort($counts);
        $top = max($counts);
        $tied = array_map(intval(...), array_keys(array_filter($counts, fn ($c) => $c === $top)));

        if (count($tied) > 1) {
            // same tie set as the previous round → nobody leaves
            $prev = $snapshot->revoteCandidates;
            $curr = $tied;
            sort($prev);
            sort($curr);
            if ($snapshot->voteRound >= 1 && $prev === $curr) {
                return new VoteOutcome(null, [], $top, count($snapshot->votes));
            }

            return new VoteOutcome(null, $tied, $top, count($snapshot->votes));
        }

        return new VoteOutcome($tied[0], [], $top, count($snapshot->votes));
    }
}

/** Immutable tally outcome. */
final readonly class VoteOutcome
{
    /**
     * @param  int|null  $eliminatedSeat  null when tie or nobody voted
     * @param  list<int>  $tieCandidates  non-empty when a revote is required
     */
    public function __construct(
        public ?int $eliminatedSeat,
        public array $tieCandidates,
        public int $topCount,
        public int $totalVotes,
    ) {}

    public function requiresRevote(): bool
    {
        return $this->eliminatedSeat === null && $this->tieCandidates !== [];
    }
}
