<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Unit;

use Phil\ProposalReview\Aggregation;
use Phil\ProposalReview\Campaign;
use Phil\ProposalReview\ConflictOfInterest;
use Phil\ProposalReview\Decision;
use Phil\ProposalReview\DecisionRecord;
use Phil\ProposalReview\Event\DecisionRecorded;
use Phil\ProposalReview\Event\DecisionsApplied;
use Phil\ProposalReview\Event\PhaseChanged;
use Phil\ProposalReview\Event\ReviewSubmitted;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Exception\NotFound;
use Phil\ProposalReview\Exception\Refused;
use Phil\ProposalReview\Phase;
use Phil\ProposalReview\Proposal;
use Phil\ProposalReview\Review;
use Phil\ProposalReview\ReviewBoard;
use Phil\ProposalReview\ReviewPolicy;
use Phil\ProposalReview\Score;
use Phil\ProposalReview\Standing;
use Phil\ProposalReview\Tests\Unit\Fixture\Jury;
use Phil\ProposalReview\Visibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SortDirection;

#[CoversClass(ReviewBoard::class)]
#[CoversClass(PhaseChanged::class)]
#[CoversClass(ReviewSubmitted::class)]
#[CoversClass(DecisionRecorded::class)]
#[CoversClass(DecisionsApplied::class)]
final class ReviewBoardTest extends TestCase
{
    public function testThePhaseFollowsTheClockAndAdvanceTellsEachChangeOnce(): void
    {
        $jury = new Jury(now: '2026-10-20 00:00:00 UTC');

        self::assertSame(Phase::NotStarted, $jury->board->phaseOf('cfp'));
        self::assertNull($jury->board->advance('cfp'));

        $jury->at('2026-11-12 00:00:00 UTC');
        self::assertSame(Phase::Review, $jury->board->phaseOf('cfp'));
        self::assertEquals(new PhaseChanged('cfp', Phase::NotStarted, Phase::Review), $jury->board->advance('cfp'));
        self::assertNull($jury->board->advance('cfp'));
        self::assertSame(Phase::Review, $jury->campaigns->get('cfp')?->observedPhase);

        $jury->at('2026-12-01 00:00:00 UTC');
        $event = $jury->board->advance('cfp');
        self::assertSame(['cfp', Phase::Review, Phase::Closed], [$event?->campaign, $event?->from, $event?->to]);
        self::assertEquals([new PhaseChanged('cfp', Phase::NotStarted, Phase::Review), new PhaseChanged('cfp', Phase::Review, Phase::Closed)], $jury->events->dispatched);
    }

    public function testAnUnknownCampaignOrProposalIsNotFound(): void
    {
        $jury = new Jury();

        foreach ([
            [static fn (): Campaign => $jury->board->campaign('nope'), 'There is no campaign "nope".'],
            [static fn (): Proposal => $jury->board->proposal('cfp', 'nope'), 'Campaign "cfp" has no proposal "nope".'],
            [static fn (): Proposal => $jury->board->proposal('nope', 'p'), 'There is no campaign "nope".'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (NotFound $notFound) {
                self::assertSame($message, $notFound->getMessage());
            }
        }
    }

    public function testProposalsAreEnteredDuringSubmissionOnly(): void
    {
        $jury = new Jury();
        $proposal = $jury->board->submit('cfp', 'talk-1', ['u-1']);

        self::assertSame(['u-1'], $proposal->authors);
        self::assertSame($proposal, $jury->board->proposal('cfp', 'talk-1'));

        $jury->at('2026-11-10 00:00:00 UTC');
        $this->assertRefused(static fn (): Proposal => $jury->board->submit('cfp', 'talk-2', []), 'Proposals are entered during the submission phase.');
    }

    public function testConflictsAndAuthorshipKeepAReviewerAway(): void
    {
        $jury = $this->juryWithProposals();
        $jury->board->declareConflict(ConflictOfInterest::withAuthor('cfp', 'r-1', 'u-2'));
        $jury->board->declareConflict(ConflictOfInterest::withProposal('cfp', 'r-2', 'talk-3'));

        self::assertTrue($jury->board->isInConflict('r-1', $jury->board->proposal('cfp', 'talk-2')));
        self::assertFalse($jury->board->isInConflict('r-1', $jury->board->proposal('cfp', 'talk-3')));
        self::assertTrue($jury->board->isInConflict('r-2', $jury->board->proposal('cfp', 'talk-3')));
        self::assertTrue($jury->board->isInConflict('u-1', $jury->board->proposal('cfp', 'talk-1')));
        self::assertFalse($jury->board->isInConflict('r-3', $jury->board->proposal('cfp', 'talk-1')));

        $this->assertRefused(static fn () => $jury->board->assign('cfp', 'talk-2', 'r-1'), 'A reviewer in conflict with a proposal is not assigned to it.');
        $this->assertRefused(static fn () => $jury->board->assign('cfp', 'talk-1', 'u-1'), 'A reviewer in conflict with a proposal is not assigned to it.');

        $jury->at('2026-11-23 00:00:00 UTC');
        $this->assertRefused(static fn () => $jury->board->declareConflict(ConflictOfInterest::withAuthor('cfp', 'r-1', 'u-3')), 'Conflicts of interest are declared before deliberation.');
        $this->assertRefused(static fn () => $jury->board->assign('cfp', 'talk-1', 'r-3'), 'Reviewers are assigned before deliberation.');
        $this->assertRefused(static fn (): int => $jury->board->assignBalanced('cfp', ['r-3'], 1), 'Reviewers are assigned before deliberation.');
    }

    public function testConflictsMayBeDeclaredUntilDeliberation(): void
    {
        $jury = $this->juryWithProposals()->at('2026-11-22 23:59:59 UTC');
        $jury->board->declareConflict(ConflictOfInterest::withProposal('cfp', 'r-9', 'talk-1'));
        $jury->board->assign('cfp', 'talk-2', 'r-9');

        self::assertTrue($jury->board->isInConflict('r-9', $jury->board->proposal('cfp', 'talk-1')));
        self::assertSame(['r-9'], $jury->proposals->reviewersOf('cfp', 'talk-2'));
    }

    public function testBalancedAssignmentSpreadsTheLoadAndSkipsConflicts(): void
    {
        $jury = $this->juryWithProposals();
        $jury->board->declareConflict(ConflictOfInterest::withProposal('cfp', 'r-a', 'talk-3'));
        $jury->board->assign('cfp', 'talk-1', 'r-b');

        self::assertSame(7, $jury->board->assignBalanced('cfp', ['r-c', 'r-a', 'r-b'], 2));
        self::assertSame(['r-a', 'r-b'], $jury->proposals->reviewersOf('cfp', 'talk-1'));
        self::assertSame(['r-a', 'r-c'], $jury->proposals->reviewersOf('cfp', 'talk-2'));
        self::assertSame(['r-b', 'r-c'], $jury->proposals->reviewersOf('cfp', 'talk-3'));
        self::assertSame(['r-a', 'r-b'], $jury->proposals->reviewersOf('cfp', 'talk-4'));
        self::assertSame(['r-a' => 3, 'r-b' => 3, 'r-c' => 2], $this->sorted($jury->proposals->assignmentCounts('cfp')));
        self::assertSame(0, $jury->board->assignBalanced('cfp', ['r-c', 'r-a', 'r-b'], 2));
        self::assertSame(3, $jury->board->assignBalanced('cfp', ['r-a', 'r-b', 'r-c'], ReviewPolicy::MAX_REVIEWS));

        foreach ([0, ReviewPolicy::MAX_REVIEWS + 1] as $count) {
            try {
                $jury->board->assignBalanced('cfp', ['r-a'], $count);
                self::fail('The number of reviewers is bounded.');
            } catch (InvalidInput $invalidInput) {
                self::assertSame('A proposal gets 1 to 1000 reviewers.', $invalidInput->getMessage());
            }
        }

        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIs('Reviewer identifiers are given once each.');
        $jury->board->assignBalanced('cfp', ['r-a', 'r-a'], 1);
    }

    public function testReviewersScoreDuringReviewWhatTheyMayReview(): void
    {
        $jury = $this->juryWithProposals();
        $jury->board->assign('cfp', 'talk-1', 'r-1');
        $this->assertRefused(fn (): Review => $jury->board->review('cfp', 'talk-1', 'r-1', $this->scores(5, 10)), 'Proposals are scored during the review phase.');

        $jury->at('2026-11-12 09:30:00 UTC');
        $review = $jury->board->review('cfp', 'talk-1', 'r-1', $this->scores(4, 6));
        self::assertSame('2026-11-12T09:30:00+00:00', $review->submittedAt->format(\DATE_ATOM));
        self::assertEquals([new ReviewSubmitted('cfp', 'talk-1', 'r-1')], $jury->events->dispatched);

        $jury->board->review('cfp', 'talk-1', 'r-1', $this->scores(5, 10));
        self::assertCount(1, $jury->reviews->ofProposal('cfp', 'talk-1'));
        self::assertSame(5, $jury->reviews->ofProposal('cfp', 'talk-1')[0]->scoreFor('content'));

        $this->assertRefused(fn (): Review => $jury->board->review('cfp', 'talk-2', 'r-1', $this->scores(5, 10)), 'This reviewer may not review this proposal.');

        $this->expectException(InvalidInput::class);
        $jury->board->review('cfp', 'talk-1', 'r-1', [new Score('content', 5)]);
    }

    public function testTheMostReviewsAndTheAssignmentRuleDecideWhoMayReview(): void
    {
        $jury = $this->juryWithProposals(new ReviewPolicy(minReviews: 1, maxReviews: 2, assignedOnly: false));
        $jury->at('2026-11-12 00:00:00 UTC');

        self::assertTrue($jury->board->mayReview('r-1', 'cfp', 'talk-1'));
        self::assertFalse($jury->board->mayReview('u-1', 'cfp', 'talk-1'));
        $jury->board->review('cfp', 'talk-1', 'r-1', $this->scores(3, 3));
        $jury->board->review('cfp', 'talk-1', 'r-2', $this->scores(3, 3));

        self::assertFalse($jury->board->mayReview('r-3', 'cfp', 'talk-1'));
        self::assertTrue($jury->board->mayReview('r-2', 'cfp', 'talk-1'));

        $assigned = $this->juryWithProposals();
        self::assertFalse($assigned->board->mayReview('r-1', 'cfp', 'talk-1'));
        $assigned->board->assign('cfp', 'talk-1', 'r-1');
        self::assertTrue($assigned->board->mayReview('r-1', 'cfp', 'talk-1'));
    }

    public function testSaveAndNextGoesToTheLeastReviewedProposal(): void
    {
        $jury = $this->juryWithProposals(new ReviewPolicy(minReviews: 1, assignedOnly: false));
        $jury->board->declareConflict(ConflictOfInterest::withProposal('cfp', 'r-1', 'talk-3'));
        $jury->at('2026-11-12 00:00:00 UTC');

        self::assertSame('talk-1', $jury->board->nextFor('r-1', 'cfp'));
        $jury->board->review('cfp', 'talk-1', 'r-2', $this->scores(3, 3));
        self::assertSame('talk-2', $jury->board->nextFor('r-1', 'cfp'));
        $jury->board->review('cfp', 'talk-2', 'r-1', $this->scores(3, 3));
        self::assertSame('talk-4', $jury->board->nextFor('r-1', 'cfp'));
        $jury->board->review('cfp', 'talk-4', 'r-1', $this->scores(3, 3));
        self::assertSame('talk-1', $jury->board->nextFor('r-1', 'cfp'));
        $jury->board->review('cfp', 'talk-1', 'r-1', $this->scores(3, 3));
        self::assertNull($jury->board->nextFor('r-1', 'cfp'));
        self::assertSame('talk-3', $jury->board->nextFor('r-2', 'cfp'));
        self::assertSame('talk-3', $jury->board->nextFor('u-1', 'cfp'));
    }

    public function testTheOtherReviewsAreShownAsThePolicySays(): void
    {
        /** @var list<array{Visibility, int, int}> $cases */
        $cases = [[Visibility::Always, 2, 3], [Visibility::AfterOwnReview, 0, 3], [Visibility::Never, 0, 1]];

        foreach ($cases as [$visibility, $before, $after]) {
            $jury = $this->juryWithProposals(new ReviewPolicy(minReviews: 1, visibility: $visibility, assignedOnly: false));
            $jury->board->declareConflict(ConflictOfInterest::withProposal('cfp', 'r-9', 'talk-1'));
            $jury->at('2026-11-12 00:00:00 UTC');
            $jury->board->review('cfp', 'talk-1', 'r-1', $this->scores(3, 3));
            $jury->board->review('cfp', 'talk-1', 'r-2', $this->scores(4, 3));

            self::assertCount($before, $jury->board->reviewsVisibleTo('r-3', 'cfp', 'talk-1'), $visibility->value);
            $jury->board->review('cfp', 'talk-1', 'r-3', $this->scores(5, 3));
            self::assertCount($after, $jury->board->reviewsVisibleTo('r-3', 'cfp', 'talk-1'), $visibility->value);
            self::assertSame([], $jury->board->reviewsVisibleTo('r-9', 'cfp', 'talk-1'));
        }

        $never = $this->juryWithProposals(new ReviewPolicy(minReviews: 1, visibility: Visibility::Never, assignedOnly: false))->at('2026-11-12 00:00:00 UTC');
        $never->board->review('cfp', 'talk-1', 'r-1', $this->scores(3, 3));
        $never->board->review('cfp', 'talk-1', 'r-2', $this->scores(4, 3));
        self::assertSame(['r-2'], array_map(static fn (Review $review): string => $review->reviewer, $never->board->reviewsVisibleTo('r-2', 'cfp', 'talk-1')));
    }

    public function testABlindCampaignHidesTheAuthorsUntilItsDecisionsAreApplied(): void
    {
        $jury = $this->juryWithProposals();
        $jury->board->declareConflict(ConflictOfInterest::withProposal('cfp', 'r-9', 'talk-1'));

        self::assertSame([], $jury->board->authorsVisibleTo('r-1', 'cfp', 'talk-1'));

        $jury->at('2026-11-24 00:00:00 UTC');
        $jury->board->decide('cfp', 'talk-1', Decision::Accepted, 'chair');
        self::assertSame([], $jury->board->authorsVisibleTo('r-1', 'cfp', 'talk-1'));

        $jury->board->applyDecisions('cfp');
        self::assertSame(['u-1'], $jury->board->authorsVisibleTo('r-1', 'cfp', 'talk-1'));
        self::assertSame([], $jury->board->authorsVisibleTo('r-9', 'cfp', 'talk-1'));
        self::assertSame(['u-1'], $jury->board->authorsVisibleTo('u-1', 'cfp', 'talk-1'));

        $open = $this->juryWithProposals(new ReviewPolicy(blind: false));
        self::assertSame(['u-2'], $open->board->authorsVisibleTo('r-1', 'cfp', 'talk-2'));
    }

    public function testAuthorsRespondDuringTheResponsePhaseOnly(): void
    {
        $jury = $this->juryWithProposals()->at('2026-11-21 00:00:00 UTC');

        self::assertTrue($jury->board->mayRespond('u-1', 'cfp', 'talk-1'));
        self::assertFalse($jury->board->mayRespond('u-2', 'cfp', 'talk-1'));

        $jury->at('2026-11-12 00:00:00 UTC');
        self::assertFalse($jury->board->mayRespond('u-1', 'cfp', 'talk-1'));
    }

    public function testStandingsRankTheProposals(): void
    {
        $jury = $this->juryWithProposals(new ReviewPolicy(minReviews: 2, aggregation: Aggregation::Median, assignedOnly: false));
        $jury->at('2026-11-12 00:00:00 UTC');
        $jury->board->review('cfp', 'talk-1', 'r-1', $this->scores(5, 10));
        $jury->board->review('cfp', 'talk-1', 'r-2', $this->scores(1, 0));
        $jury->board->review('cfp', 'talk-2', 'r-1', $this->scores(3, 5));
        $jury->board->review('cfp', 'talk-2', 'r-2', $this->scores(3, 5));
        $jury->board->review('cfp', 'talk-4', 'r-1', $this->scores(5, 10));

        self::assertEquals([
            new Standing('talk-4', 1, 1.0, false, null),
            new Standing('talk-1', 2, 0.5, true, null),
            new Standing('talk-2', 2, 0.5, true, null),
            new Standing('talk-3', 0, null, false, null),
        ], $jury->board->standings('cfp'));
        self::assertSame(['talk-1', 'talk-2', 'talk-4', 'talk-3'], array_map(static fn (Standing $standing): string => $standing->proposal, $jury->board->standings('cfp', SortDirection::Ascending)));

        $jury->at('2026-11-24 00:00:00 UTC');
        $decision = $jury->board->decide('cfp', 'talk-3', Decision::Rejected, 'chair');
        self::assertSame($decision, $jury->board->standings('cfp')[3]->decision);
    }

    public function testDecisionsAreRecordedThenAppliedTogether(): void
    {
        $jury = $this->juryWithProposals()->at('2026-11-15 00:00:00 UTC');
        $this->assertRefused(static fn (): DecisionRecord => $jury->board->decide('cfp', 'talk-1', Decision::Accepted, 'chair'), 'Decisions are recorded during deliberation.');
        $this->assertRefused(static fn (): array => $jury->board->applyDecisions('cfp'), 'Decisions are applied from deliberation on.');

        $jury->at('2026-11-24 10:00:00 UTC');
        $jury->board->decide('cfp', 'talk-2', Decision::Waitlisted, 'chair');
        $jury->board->decide('cfp', 'talk-2', Decision::Accepted, 'chair');
        $jury->board->decide('cfp', 'talk-1', Decision::Rejected, 'chair');
        self::assertSame([Decision::Rejected, Decision::Accepted], array_map(static fn (DecisionRecord $record): Decision => $record->decision, $jury->reviews->decisions('cfp')));

        $jury->at('2026-11-25 10:00:00 UTC');
        self::assertSame(['talk-1', 'talk-2'], $jury->board->applyDecisions('cfp'));
        self::assertSame('2026-11-25T10:00:00+00:00', $jury->reviews->decisions('cfp')[0]->appliedAt?->format(\DATE_ATOM));
        self::assertSame([], $jury->board->applyDecisions('cfp'));
        $this->assertRefused(static fn (): DecisionRecord => $jury->board->decide('cfp', 'talk-1', Decision::Accepted, 'chair'), 'The decision of this proposal is already applied.');

        $jury->board->decide('cfp', 'talk-3', Decision::Accepted, 'chair');
        $jury->at('2026-12-02 00:00:00 UTC');
        self::assertSame(['talk-3'], $jury->board->applyDecisions('cfp'));
        self::assertEquals([
            new DecisionRecorded('cfp', 'talk-2'),
            new DecisionRecorded('cfp', 'talk-2'),
            new DecisionRecorded('cfp', 'talk-1'),
            new DecisionsApplied('cfp', ['talk-1', 'talk-2']),
            new DecisionRecorded('cfp', 'talk-3'),
            new DecisionsApplied('cfp', ['talk-3']),
        ], $jury->events->dispatched);
    }

    public function testABoardWithoutDispatcherWorks(): void
    {
        $jury = new Jury();
        $board = new ReviewBoard($jury->campaigns, $jury->proposals, $jury->reviews, $jury->clock);
        $board->submit('cfp', 'p', []);

        $jury->at('2026-11-12 00:00:00 UTC');

        self::assertInstanceOf(PhaseChanged::class, $board->advance('cfp'));
        self::assertSame([], $jury->events->dispatched);
    }

    private function juryWithProposals(ReviewPolicy $policy = new ReviewPolicy(minReviews: 2)): Jury
    {
        $jury = new Jury($policy);
        $jury->board->submit('cfp', 'talk-2', ['u-2']);
        $jury->board->submit('cfp', 'talk-1', ['u-1']);
        $jury->board->submit('cfp', 'talk-3', ['u-3']);
        $jury->board->submit('cfp', 'talk-4', ['u-4']);

        return $jury;
    }

    /**
     * @return list<Score>
     */
    private function scores(int $content, int $clarity): array
    {
        return [new Score('content', $content), new Score('clarity', $clarity)];
    }

    /**
     * @param array<string, int> $counts
     *
     * @return array<string, int>
     */
    private function sorted(array $counts): array
    {
        ksort($counts);

        return $counts;
    }

    private function assertRefused(callable $call, string $message): void
    {
        try {
            $call();
            self::fail($message);
        } catch (Refused $refused) {
            self::assertSame($message, $refused->getMessage());
        }
    }

    public function testBalancedAssignmentServesTheLeastAssignedProposalFirst(): void
    {
        $jury = new Jury();
        $jury->board->submit('cfp', 'p-x', []);
        $jury->board->submit('cfp', 'p-y', []);
        $jury->board->assign('cfp', 'p-x', 'r-1');

        self::assertSame(3, $jury->board->assignBalanced('cfp', ['r-3', 'r-1', 'r-2'], 2));
        self::assertSame(['r-1', 'r-2'], $jury->proposals->reviewersOf('cfp', 'p-x'));
        self::assertSame(['r-2', 'r-3'], $jury->proposals->reviewersOf('cfp', 'p-y'));
    }

    public function testBalancedAssignmentLeavesAProposalWithMoreReviewersThanAskedAlone(): void
    {
        $jury = new Jury();
        $jury->board->submit('cfp', 'p-x', []);
        $jury->board->assign('cfp', 'p-x', 'r-1');
        $jury->board->assign('cfp', 'p-x', 'r-2');

        self::assertSame(0, $jury->board->assignBalanced('cfp', ['r-1', 'r-2', 'r-3', 'r-4'], 1));
        self::assertSame(['r-1', 'r-2'], $jury->proposals->reviewersOf('cfp', 'p-x'));
    }

    public function testTheCampaignIsLookedUpBeforeTheReviewersAreChecked(): void
    {
        $jury = new Jury();

        $this->expectException(NotFound::class);
        $jury->board->assignBalanced('nope', ['r@x.example'], 0);
    }

    public function testReviewerIdentifiersAreCheckedWhenAssigningAndReviewing(): void
    {
        $jury = $this->juryWithProposals(new ReviewPolicy(minReviews: 1, assignedOnly: false));

        try {
            $jury->board->assign('cfp', 'talk-1', 'r@x.example');
            self::fail('A reviewer is never an e-mail address.');
        } catch (InvalidInput $invalidInput) {
            self::assertSame('Reviewer identifiers are stable opaque identifiers, never e-mail addresses.', $invalidInput->getMessage());
        }

        self::assertSame([], $jury->proposals->reviewersOf('cfp', 'talk-1'));

        $jury->at('2026-11-12 00:00:00 UTC');
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIs('Reviewer identifiers are stable opaque identifiers, never e-mail addresses.');
        $jury->board->review('cfp', 'talk-1', 'r@x.example', []);
    }

    public function testADecisionNamesAProposalOfTheCampaign(): void
    {
        $jury = $this->juryWithProposals()->at('2026-11-24 00:00:00 UTC');

        $this->expectException(NotFound::class);
        $jury->board->decide('cfp', 'nope', Decision::Accepted, 'chair');
    }

    public function testAnotherBoardWithoutDispatcherReviewsDecidesAndApplies(): void
    {
        $jury = new Jury(new ReviewPolicy(minReviews: 1, assignedOnly: false));
        $board = new ReviewBoard($jury->campaigns, $jury->proposals, $jury->reviews, $jury->clock);
        $board->submit('cfp', 'p', []);

        $jury->at('2026-11-12 00:00:00 UTC');
        self::assertSame('r-1', $board->review('cfp', 'p', 'r-1', $this->scores(3, 3))->reviewer);

        $jury->at('2026-11-24 00:00:00 UTC');
        self::assertSame('p', $board->decide('cfp', 'p', Decision::Accepted, 'chair')->proposal);
        self::assertSame(['p'], $board->applyDecisions('cfp'));
        self::assertSame([], $jury->events->dispatched);
    }
}
