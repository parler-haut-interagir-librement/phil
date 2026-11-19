<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Benchmark;

use DateTimeImmutable;
use Phil\ProposalReview\Aggregation;
use Phil\ProposalReview\Campaign;
use Phil\ProposalReview\Criterion;
use Phil\ProposalReview\Memory\InMemoryCampaignStore;
use Phil\ProposalReview\Memory\InMemoryProposalStore;
use Phil\ProposalReview\Memory\InMemoryReviewStore;
use Phil\ProposalReview\Memory\InMemoryVoteLog;
use Phil\ProposalReview\Pairwise\BradleyTerry;
use Phil\ProposalReview\Pairwise\PairwiseVoting;
use Phil\ProposalReview\Pairwise\Vote;
use Phil\ProposalReview\Phase;
use Phil\ProposalReview\PhaseWindow;
use Phil\ProposalReview\Proposal;
use Phil\ProposalReview\Review;
use Phil\ProposalReview\ReviewBoard;
use Phil\ProposalReview\ReviewPolicy;
use Phil\ProposalReview\Schedule;
use Phil\ProposalReview\Score;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\Clock\MockClock;

/**
 * The hot paths of a large call: the standings of five hundred proposals with three reviews of four criteria each;
 * the Bradley–Terry fit of two hundred proposals over twenty thousand votes; the next pair of a voter who has judged
 * five hundred pairs among two hundred proposals, in a log of twenty thousand votes.
 */
final readonly class ReviewBench
{
    private ReviewBoard $board;

    private PairwiseVoting $voting;

    /** @var list<string> */
    private array $entries;

    /** @var list<Vote> */
    private array $votes;

    public function __construct()
    {
        $clock = new MockClock('2026-11-12 10:00:00 UTC');
        $campaigns = new InMemoryCampaignStore();
        $proposals = new InMemoryProposalStore();
        $reviews = new InMemoryReviewStore();
        $schedule = new Schedule([
            new PhaseWindow(Phase::Review, new DateTimeImmutable('2026-11-10 00:00:00 UTC'), new DateTimeImmutable('2026-11-20 00:00:00 UTC')),
            new PhaseWindow(Phase::Deliberation, new DateTimeImmutable('2026-11-20 00:00:00 UTC'), new DateTimeImmutable('2026-11-30 00:00:00 UTC')),
        ]);
        $criteria = [new Criterion('a', 3), new Criterion('b', 2), new Criterion('c'), new Criterion('d', 1, 0, 10)];
        $campaigns->save(new Campaign('cfp', $schedule, $criteria, new ReviewPolicy(aggregation: Aggregation::Median)));
        $campaigns->save(new Campaign('jam', $schedule, $criteria));

        $random = new Randomizer(new Mt19937(11));
        $at = new DateTimeImmutable('2026-11-12 10:00:00 UTC');

        for ($proposal = 0; $proposal < 500; ++$proposal) {
            $proposals->save(new Proposal('cfp', 'p'.$proposal, ['u'.$proposal]));

            for ($reviewer = 0; $reviewer < 3; ++$reviewer) {
                $reviews->save(new Review('cfp', 'p'.$proposal, 'r'.$reviewer, [
                    new Score('a', $random->getInt(0, 5)),
                    new Score('b', $random->getInt(0, 5)),
                    new Score('c', $random->getInt(0, 5)),
                    new Score('d', $random->getInt(0, 10)),
                ], $at));
            }
        }

        $entries = [];

        for ($entry = 0; $entry < 200; ++$entry) {
            $proposals->save(new Proposal('jam', 'e'.$entry, []));
            $entries[] = 'e'.$entry;
        }

        $log = new InMemoryVoteLog();
        $votes = [];

        for ($vote = 0; $vote < 20_000; ++$vote) {
            $first = $random->getInt(0, 199);
            $second = ($first + $random->getInt(1, 199)) % 200;
            $winner = $random->getInt(0, $first + $second) < $second ? $second : $first;
            $loser = $winner === $first ? $second : $first;
            $voter = $vote < 500 ? 'busy' : 'v'.($vote % 300);
            $votes[] = $log->append('jam', $voter, 'e'.$winner, 'e'.$loser, $at);
        }

        $this->board = new ReviewBoard($campaigns, $proposals, $reviews, $clock);
        $this->voting = new PairwiseVoting($this->board, $proposals, $log, $clock);
        $this->entries = $entries;
        $this->votes = $votes;
    }

    public function benchStandings(): void
    {
        $this->board->standings('cfp');
    }

    public function benchBradleyTerry(): void
    {
        new BradleyTerry()->fit($this->entries, $this->votes);
    }

    public function benchNextPair(): void
    {
        $this->voting->nextPair('busy', 'jam');
    }
}
