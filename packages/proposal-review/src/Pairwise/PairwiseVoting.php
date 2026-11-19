<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Pairwise;

use Phil\ProposalReview\Contract\ProposalStore;
use Phil\ProposalReview\Contract\VoteLog;
use Phil\ProposalReview\Event\PairwiseVoteCast;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Exception\NotFound;
use Phil\ProposalReview\Exception\Refused;
use Phil\ProposalReview\Internal\Identifier;
use Phil\ProposalReview\Phase;
use Phil\ProposalReview\Proposal;
use Phil\ProposalReview\ReviewBoard;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SortDirection;

/**
 * Voting by pairs, beside or instead of scores: a voter is shown two proposals and picks one. The pairs are drawn to
 * balance exposure — the proposals seen least so far first, never a pair the voter already judged, never a proposal
 * they are in conflict with —, and the votes go to an append-only log from which the Bradley–Terry model computes the
 * scores, as often as asked and always the same for the same log.
 *
 * Votes are cast during the review phase of the campaign. The service keeps no state between two calls, and is safe to
 * share across the requests of a long-running worker.
 */
final readonly class PairwiseVoting
{
    public function __construct(
        private ReviewBoard $board,
        private ProposalStore $proposals,
        private VoteLog $votes,
        private ClockInterface $clock,
        private BradleyTerry $model = new BradleyTerry(),
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * The next two proposals to show a voter, or null when they judged every pair they may judge. Among the proposals
     * the voter may judge, those shown least often in the whole log come first; between equals, an order drawn from
     * the campaign, the voter and the number of votes they cast, so that two voters do not see the same pair first and
     * the draw can be checked from the log. The first proposal in that order meets the first one the voter has not yet
     * compared it with.
     *
     * Its cost grows with the square of the proposals in the worst case, when the voter has judged almost every pair.
     *
     * @throws NotFound
     *
     * @phpstan-impure
     */
    public function nextPair(string $voter, string $campaign): ?Matchup
    {
        $this->board->campaign($campaign);
        $eligible = $this->eligible($voter, $campaign);
        $log = $this->votes->ofCampaign($campaign);
        $exposure = array_fill_keys(array_map(static fn (Proposal $proposal): string => $proposal->id, $eligible), 0);
        $judged = [];
        $cast = 0;

        foreach ($log as $vote) {
            foreach ([$vote->winner, $vote->loser] as $proposal) {
                if (isset($exposure[$proposal])) {
                    ++$exposure[$proposal];
                }
            }

            if ($vote->voter === $voter) {
                $judged[$this->pair($vote->winner, $vote->loser)] = true;
                ++$cast;
            }
        }

        $order = array_map(static fn (Proposal $proposal): array => [
            $exposure[$proposal->id],
            hash('sha256', $campaign."\0".$voter."\0".$cast."\0".$proposal->id),
            $proposal->id,
        ], $eligible);
        usort($order, static fn (array $a, array $b): int => $a <=> $b);
        $ids = array_column($order, 2);

        foreach ($ids as $position => $first) {
            foreach ($ids as $other => $second) {
                if ($other !== $position && !isset($judged[$this->pair($first, $second)])) {
                    return new Matchup($first, $second);
                }
            }
        }

        return null;
    }

    /**
     * Records that a voter preferred one proposal to another.
     *
     * @throws NotFound
     * @throws Refused      outside the review phase, or when the voter is in conflict with either proposal
     * @throws InvalidInput when both are the same proposal
     */
    public function vote(string $voter, string $campaign, string $winner, string $loser): Vote
    {
        Identifier::check($voter, 'voter');

        if ($winner === $loser) {
            throw InvalidInput::because('A vote compares two different proposals.');
        }

        if (Phase::Review !== $this->board->phaseOf($campaign)) {
            throw Refused::because('Pairwise votes are cast during the review phase.');
        }

        foreach ([$winner, $loser] as $proposal) {
            if ($this->board->isInConflict($voter, $this->board->proposal($campaign, $proposal))) {
                throw Refused::because('A voter in conflict with a proposal does not judge it.');
            }
        }

        $vote = $this->votes->append($campaign, $voter, $winner, $loser, $this->clock->now());
        $this->events?->dispatch(new PairwiseVoteCast($campaign, $vote->sequence));

        return $vote;
    }

    /**
     * The scores of every proposal of the campaign from its whole log, the strongest first by default, between equals
     * the first identifier.
     *
     * @return list<PairwiseScore>
     *
     * @throws NotFound
     *
     * @phpstan-impure
     */
    public function scores(string $campaign, SortDirection $direction = SortDirection::Descending): array
    {
        $this->board->campaign($campaign);
        $proposals = array_map(static fn (Proposal $proposal): string => $proposal->id, $this->proposals->ofCampaign($campaign));
        $scores = $this->model->fit($proposals, $this->votes->ofCampaign($campaign));
        usort($scores, static fn (PairwiseScore $a, PairwiseScore $b): int => [SortDirection::Ascending === $direction ? $a->strength <=> $b->strength : $b->strength <=> $a->strength, $a->proposal] <=> [0, $b->proposal]);

        return $scores;
    }

    /**
     * @return list<Proposal>
     */
    private function eligible(string $voter, string $campaign): array
    {
        return array_values(array_filter(
            $this->proposals->ofCampaign($campaign),
            fn (Proposal $proposal): bool => !$this->board->isInConflict($voter, $proposal),
        ));
    }

    private function pair(string $first, string $second): string
    {
        return strcmp($first, $second) < 0 ? $first."\0".$second : $second."\0".$first;
    }
}
