<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

use Phil\ProposalReview\Contract\CampaignStore;
use Phil\ProposalReview\Contract\ProposalStore;
use Phil\ProposalReview\Contract\ReviewStore;
use Phil\ProposalReview\Event\DecisionRecorded;
use Phil\ProposalReview\Event\DecisionsApplied;
use Phil\ProposalReview\Event\PhaseChanged;
use Phil\ProposalReview\Event\ReviewSubmitted;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Exception\NotFound;
use Phil\ProposalReview\Exception\Refused;
use Phil\ProposalReview\Internal\Identifier;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SortDirection;

/**
 * What a jury does with a campaign: proposals entered during submission, reviewers assigned by hand or balanced,
 * conflicts of interest declared, scores given during review, the next proposal to review, standings, decisions
 * recorded during deliberation and applied together. The campaign's dated schedule, read on the clock, opens and closes
 * each action; reviewers and authors are named by the application's stable identifiers.
 *
 * The board keeps no state between two calls, and is safe to share across the requests of a long-running worker.
 */
final readonly class ReviewBoard
{
    public function __construct(
        private CampaignStore $campaigns,
        private ProposalStore $proposals,
        private ReviewStore $reviews,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @throws NotFound
     */
    public function campaign(string $id): Campaign
    {
        return $this->campaigns->get($id) ?? throw NotFound::campaign($id);
    }

    /**
     * The phase of a campaign now, from its schedule and the clock.
     *
     * @throws NotFound
     *
     * @phpstan-impure
     */
    public function phaseOf(string $campaign): Phase
    {
        return $this->campaign($campaign)->schedule->phaseAt($this->clock->now());
    }

    /**
     * Records the phase a campaign is in now, and tells `PhaseChanged` when it is not the one last recorded. Call it
     * from a scheduled task: the phases follow the clock whether it runs or not, and the event follows them.
     *
     * @throws NotFound
     *
     * @phpstan-impure
     */
    public function advance(string $campaign): ?PhaseChanged
    {
        $current = $this->campaign($campaign);
        $phase = $current->schedule->phaseAt($this->clock->now());

        if ($phase === $current->observedPhase) {
            return null;
        }

        $this->campaigns->save($current->withObservedPhase($phase));
        $event = new PhaseChanged($campaign, $current->observedPhase, $phase);
        $this->events?->dispatch($event);

        return $event;
    }

    /**
     * Enters a proposal, or its new authors, during the submission phase.
     *
     * @param list<string> $authors
     *
     * @throws NotFound
     * @throws Refused      outside the submission phase
     * @throws InvalidInput
     */
    public function submit(string $campaign, string $proposal, array $authors): Proposal
    {
        $this->requirePhase($campaign, Phase::Submission, 'Proposals are entered during the submission phase.');
        $entered = new Proposal($campaign, $proposal, $authors);
        $this->proposals->save($entered);

        return $entered;
    }

    /**
     * @throws NotFound
     */
    public function proposal(string $campaign, string $id): Proposal
    {
        $this->campaign($campaign);

        return $this->proposals->get($campaign, $id) ?? throw NotFound::proposal($campaign, $id);
    }

    /**
     * Declares a conflict of interest, before deliberation.
     *
     * @throws NotFound
     * @throws Refused  from deliberation on
     */
    public function declareConflict(ConflictOfInterest $conflict): void
    {
        if ($this->phaseOf($conflict->campaign)->position() >= Phase::Deliberation->position()) {
            throw Refused::because('Conflicts of interest are declared before deliberation.');
        }

        $this->proposals->declareConflict($conflict);
    }

    /**
     * Whether a reviewer is in conflict with a proposal: its author, or declared so.
     *
     * @phpstan-impure
     */
    public function isInConflict(string $reviewer, Proposal $proposal): bool
    {
        return $proposal->isAuthoredBy($reviewer)
            || array_any($this->proposals->conflictsOf($proposal->campaign, $reviewer), static fn (ConflictOfInterest $conflict): bool => $conflict->concerns($proposal));
    }

    /**
     * Assigns a reviewer to a proposal, before deliberation.
     *
     * @throws NotFound
     * @throws Refused      when the reviewer is in conflict with it, or from deliberation on
     * @throws InvalidInput
     */
    public function assign(string $campaign, string $proposal, string $reviewer): void
    {
        $entered = $this->proposal($campaign, $proposal);
        Identifier::check($reviewer, 'reviewer');

        if ($this->phaseOf($campaign)->position() >= Phase::Deliberation->position()) {
            throw Refused::because('Reviewers are assigned before deliberation.');
        }

        if ($this->isInConflict($reviewer, $entered)) {
            throw Refused::because('A reviewer in conflict with a proposal is not assigned to it.');
        }

        $this->proposals->assign($campaign, $proposal, $reviewer);
    }

    /**
     * Assigns reviewers until each proposal has the number asked, or no reviewer is left who may take it: each proposal,
     * the least assigned first, goes to the reviewers with the fewest assignments who are not in conflict with it,
     * between equals the first identifier. The same proposals, reviewers and assignments give the same result.
     *
     * @param list<string> $reviewers
     *
     * @return int the assignments made
     *
     * @throws NotFound
     * @throws Refused      from deliberation on
     * @throws InvalidInput
     *
     * @phpstan-impure
     */
    public function assignBalanced(string $campaign, array $reviewers, int $perProposal): int
    {
        $this->campaign($campaign);
        Identifier::unique($reviewers, 'reviewer');

        if ($perProposal < 1 || $perProposal > ReviewPolicy::MAX_REVIEWS) {
            throw InvalidInput::because(\sprintf('A proposal gets 1 to %d reviewers.', ReviewPolicy::MAX_REVIEWS));
        }

        if ($this->phaseOf($campaign)->position() >= Phase::Deliberation->position()) {
            throw Refused::because('Reviewers are assigned before deliberation.');
        }

        $counts = $this->proposals->assignmentCounts($campaign);
        $load = [];

        foreach ($reviewers as $reviewer) {
            $load[$reviewer] = $counts[$reviewer] ?? 0;
        }

        $proposals = [];

        foreach ($this->proposals->ofCampaign($campaign) as $proposal) {
            $proposals[] = [$proposal, $this->proposals->reviewersOf($campaign, $proposal->id)];
        }

        usort($proposals, static fn (array $a, array $b): int => [\count($a[1]), $a[0]->id] <=> [\count($b[1]), $b[0]->id]);
        $made = 0;

        foreach ($proposals as [$proposal, $assigned]) {
            $candidates = array_filter($reviewers, fn (string $reviewer): bool => !\in_array($reviewer, $assigned, true) && !$this->isInConflict($reviewer, $proposal));
            usort($candidates, static fn (string $a, string $b): int => [$load[$a], $a] <=> [$load[$b], $b]);

            foreach (\array_slice($candidates, 0, max(0, $perProposal - \count($assigned))) as $reviewer) {
                $this->proposals->assign($campaign, $proposal->id, $reviewer);
                ++$load[$reviewer];
                ++$made;
            }
        }

        return $made;
    }

    /**
     * Whether a reviewer may score a proposal: not in conflict with it, assigned to it when the policy asks, and below
     * the most reviews the policy allows unless the reviewer already reviewed it.
     *
     * @throws NotFound
     *
     * @phpstan-impure
     */
    public function mayReview(string $reviewer, string $campaign, string $proposal): bool
    {
        $policy = $this->campaign($campaign)->policy;
        $entered = $this->proposal($campaign, $proposal);

        if ($this->isInConflict($reviewer, $entered)) {
            return false;
        }

        if ($policy->assignedOnly && !\in_array($reviewer, $this->proposals->reviewersOf($campaign, $proposal), true)) {
            return false;
        }

        $reviews = $this->reviews->ofProposal($campaign, $proposal);

        return null === $policy->maxReviews
            || \count($reviews) < $policy->maxReviews
            || array_any($reviews, static fn (Review $review): bool => $review->reviewer === $reviewer);
    }

    /**
     * Scores a proposal during the review phase, or scores it again.
     *
     * @param list<Score> $scores one per criterion of the campaign
     *
     * @throws NotFound
     * @throws Refused      outside the review phase, or when the reviewer may not review it
     * @throws InvalidInput when a criterion is missing, unknown, given twice or out of its scale
     */
    public function review(string $campaign, string $proposal, string $reviewer, array $scores): Review
    {
        $current = $this->requirePhase($campaign, Phase::Review, 'Proposals are scored during the review phase.');
        Identifier::check($reviewer, 'reviewer');

        if (!$this->mayReview($reviewer, $campaign, $proposal)) {
            throw Refused::because('This reviewer may not review this proposal.');
        }

        Scoring::check($current->criteria, $scores);
        $review = new Review($campaign, $proposal, $reviewer, $scores, $this->clock->now());
        $this->reviews->save($review);
        $this->events?->dispatch(new ReviewSubmitted($campaign, $proposal, $reviewer));

        return $review;
    }

    /**
     * The proposal a reviewer should review next — "save and next" —: among those they may review and have not, the one
     * with the fewest reviews, between equals the first identifier; null when none is left.
     *
     * @throws NotFound
     *
     * @phpstan-impure
     */
    public function nextFor(string $reviewer, string $campaign): ?string
    {
        $next = null;
        $fewest = \PHP_INT_MAX;

        foreach ($this->proposals->ofCampaign($campaign) as $proposal) {
            $reviews = $this->reviews->ofProposal($campaign, $proposal->id);

            if (\count($reviews) >= $fewest
                || array_any($reviews, static fn (Review $review): bool => $review->reviewer === $reviewer)
                || !$this->mayReview($reviewer, $campaign, $proposal->id)) {
                continue;
            }

            $next = $proposal->id;
            $fewest = \count($reviews);
        }

        return $next;
    }

    /**
     * The reviews of a proposal a reviewer may see, by the campaign's visibility: their own, and the others' when the
     * policy shows them. A reviewer in conflict sees none.
     *
     * @return list<Review>
     *
     * @throws NotFound
     *
     * @phpstan-impure
     */
    public function reviewsVisibleTo(string $reviewer, string $campaign, string $proposal): array
    {
        $visibility = $this->campaign($campaign)->policy->visibility;

        if ($this->isInConflict($reviewer, $this->proposal($campaign, $proposal))) {
            return [];
        }

        $reviews = $this->reviews->ofProposal($campaign, $proposal);
        $own = array_values(array_filter($reviews, static fn (Review $review): bool => $review->reviewer === $reviewer));

        return match ($visibility) {
            Visibility::Always => $reviews,
            Visibility::AfterOwnReview => [] === $own ? [] : $reviews,
            Visibility::Never => $own,
        };
    }

    /**
     * The authors a reviewer may see: none in a blind campaign until its decisions are applied, none for a reviewer in
     * conflict, all of them otherwise.
     *
     * @return list<string>
     *
     * @throws NotFound
     *
     * @phpstan-impure
     */
    public function authorsVisibleTo(string $reviewer, string $campaign, string $proposal): array
    {
        $entered = $this->proposal($campaign, $proposal);

        if ($this->campaign($campaign)->policy->blind && !$this->isApplied($campaign, $proposal)) {
            return [];
        }

        return $this->isInConflict($reviewer, $entered) && !$entered->isAuthoredBy($reviewer) ? [] : $entered->authors;
    }

    /**
     * Whether an author may answer the reviews of their proposal: during the author response phase only.
     *
     * @throws NotFound
     *
     * @phpstan-impure
     */
    public function mayRespond(string $author, string $campaign, string $proposal): bool
    {
        return Phase::AuthorResponse === $this->phaseOf($campaign) && $this->proposal($campaign, $proposal)->isAuthoredBy($author);
    }

    /**
     * Where each proposal stands, the best score first by default; proposals without a score come last, and between
     * equals the first identifier comes first.
     *
     * @return list<Standing>
     *
     * @throws NotFound
     *
     * @phpstan-impure
     */
    public function standings(string $campaign, SortDirection $direction = SortDirection::Descending): array
    {
        $current = $this->campaign($campaign);
        $decisions = [];

        foreach ($this->reviews->decisions($campaign) as $decision) {
            $decisions[$decision->proposal] = $decision;
        }

        $standings = [];

        foreach ($this->proposals->ofCampaign($campaign) as $proposal) {
            $reviews = $this->reviews->ofProposal($campaign, $proposal->id);
            $scores = array_map(static fn (Review $review): float => Scoring::review($current->criteria, $review->scores), $reviews);
            $standings[] = new Standing(
                $proposal->id,
                \count($reviews),
                Scoring::aggregate($scores, $current->policy->aggregation),
                \count($reviews) >= $current->policy->minReviews,
                $decisions[$proposal->id] ?? null,
            );
        }

        usort($standings, static fn (Standing $a, Standing $b): int => [null === $a->score, SortDirection::Ascending === $direction ? $a->score <=> $b->score : $b->score <=> $a->score, $a->proposal] <=> [null === $b->score, 0, $b->proposal]);

        return $standings;
    }

    /**
     * Records a pending decision for a proposal during deliberation, replacing a pending one.
     *
     * @throws NotFound
     * @throws Refused  outside deliberation, or when the proposal's decision is already applied
     */
    public function decide(string $campaign, string $proposal, Decision $decision, string $member): DecisionRecord
    {
        $this->requirePhase($campaign, Phase::Deliberation, 'Decisions are recorded during deliberation.');
        $this->proposal($campaign, $proposal);

        if ($this->isApplied($campaign, $proposal)) {
            throw Refused::because('The decision of this proposal is already applied.');
        }

        $record = new DecisionRecord($campaign, $proposal, $decision, $member, $this->clock->now());
        $this->reviews->saveDecision($record);
        $this->events?->dispatch(new DecisionRecorded($campaign, $proposal));

        return $record;
    }

    /**
     * Applies every pending decision of the campaign at once, during deliberation or once the campaign is closed, and
     * tells `DecisionsApplied` when there was one.
     *
     * @return list<string> the proposals whose decision was applied, in identifier order
     *
     * @throws NotFound
     * @throws Refused  before deliberation
     *
     * @phpstan-impure
     */
    public function applyDecisions(string $campaign): array
    {
        if ($this->phaseOf($campaign)->position() < Phase::Deliberation->position()) {
            throw Refused::because('Decisions are applied from deliberation on.');
        }

        $now = $this->clock->now();
        $applied = [];

        foreach ($this->reviews->decisions($campaign) as $decision) {
            if ($decision->isPending()) {
                $this->reviews->saveDecision($decision->appliedAt($now));
                $applied[] = $decision->proposal;
            }
        }

        if ([] !== $applied) {
            $this->events?->dispatch(new DecisionsApplied($campaign, $applied));
        }

        return $applied;
    }

    private function isApplied(string $campaign, string $proposal): bool
    {
        return array_any($this->reviews->decisions($campaign), static fn (DecisionRecord $decision): bool => $decision->proposal === $proposal && !$decision->isPending());
    }

    /**
     * @throws NotFound
     * @throws Refused
     */
    private function requirePhase(string $campaign, Phase $phase, string $reason): Campaign
    {
        $current = $this->campaign($campaign);

        if ($current->schedule->phaseAt($this->clock->now()) !== $phase) {
            throw Refused::because($reason);
        }

        return $current;
    }
}
