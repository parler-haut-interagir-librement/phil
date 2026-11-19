<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Unit;

use Closure;
use DateTimeImmutable;
use Phil\ProposalReview\Aggregation;
use Phil\ProposalReview\Campaign;
use Phil\ProposalReview\ConflictOfInterest;
use Phil\ProposalReview\Criterion;
use Phil\ProposalReview\Decision;
use Phil\ProposalReview\DecisionRecord;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Exception\NotFound;
use Phil\ProposalReview\Exception\ProposalReviewException;
use Phil\ProposalReview\Exception\Refused;
use Phil\ProposalReview\Internal\Identifier;
use Phil\ProposalReview\Internal\Utc;
use Phil\ProposalReview\Phase;
use Phil\ProposalReview\PhaseWindow;
use Phil\ProposalReview\Proposal;
use Phil\ProposalReview\Review;
use Phil\ProposalReview\ReviewPolicy;
use Phil\ProposalReview\Schedule;
use Phil\ProposalReview\Score;
use Phil\ProposalReview\Standing;
use Phil\ProposalReview\Tests\Unit\Fixture\Jury;
use Phil\ProposalReview\Visibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Phase::class)]
#[CoversClass(PhaseWindow::class)]
#[CoversClass(Schedule::class)]
#[CoversClass(Criterion::class)]
#[CoversClass(Score::class)]
#[CoversClass(ReviewPolicy::class)]
#[CoversClass(Campaign::class)]
#[CoversClass(Proposal::class)]
#[CoversClass(ConflictOfInterest::class)]
#[CoversClass(Review::class)]
#[CoversClass(DecisionRecord::class)]
#[CoversClass(Standing::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(Utc::class)]
#[CoversClass(InvalidInput::class)]
#[CoversClass(Refused::class)]
#[CoversClass(NotFound::class)]
final class ModelTest extends TestCase
{
    public function testPhasesAreOrdered(): void
    {
        self::assertSame([0, 1, 2, 3, 4, 5], array_map(static fn (Phase $phase): int => $phase->position(), Phase::cases()));
    }

    public function testAWindowIsKeptInUtcAndHoldsItsStartButNotItsEnd(): void
    {
        $window = new PhaseWindow(Phase::Review, new DateTimeImmutable('2026-11-10 08:00:00 America/Toronto'), new DateTimeImmutable('2026-11-20 00:00:00 UTC'));

        self::assertSame('2026-11-10T13:00:00+00:00', $window->start->format(\DATE_ATOM));
        self::assertSame('UTC', $window->end->getTimezone()->getName());
        self::assertTrue($window->contains(new DateTimeImmutable('2026-11-10 13:00:00 UTC')));
        self::assertFalse($window->contains(new DateTimeImmutable('2026-11-10 12:59:59 UTC')));
        self::assertTrue($window->contains(new DateTimeImmutable('2026-11-19 23:59:59 UTC')));
        self::assertFalse($window->contains(new DateTimeImmutable('2026-11-20 00:00:00 UTC')));
    }

    public function testTheScheduleGivesThePhaseAtAnInstant(): void
    {
        $schedule = Jury::schedule();

        self::assertSame(Phase::NotStarted, $schedule->phaseAt(new DateTimeImmutable('2026-10-31 23:59:59 UTC')));
        self::assertSame(Phase::Submission, $schedule->phaseAt(new DateTimeImmutable('2026-11-01 00:00:00 UTC')));
        self::assertSame(Phase::Review, $schedule->phaseAt(new DateTimeImmutable('2026-11-10 00:00:00 UTC')));
        self::assertSame(Phase::AuthorResponse, $schedule->phaseAt(new DateTimeImmutable('2026-11-22 23:00:00 UTC')));
        self::assertSame(Phase::Deliberation, $schedule->phaseAt(new DateTimeImmutable('2026-11-29 23:59:59 UTC')));
        self::assertSame(Phase::Closed, $schedule->phaseAt(new DateTimeImmutable('2026-11-30 00:00:00 UTC')));
        self::assertTrue($schedule->has(Phase::AuthorResponse));
        self::assertFalse(new Schedule([self::window(Phase::Review, 1, 2), self::window(Phase::Deliberation, 2, 3)])->has(Phase::Submission));
        self::assertCount(4, $schedule->windows);
    }

    public function testCriteriaNormalizeTheirScale(): void
    {
        $criterion = new Criterion('clarity', 3, -2, 2);

        self::assertSame([3, -2, 2], [$criterion->weight, $criterion->min, $criterion->max]);
        self::assertSame(0.0, $criterion->normalize(-2));
        self::assertSame(0.75, $criterion->normalize(1));
        self::assertSame(1.0, $criterion->normalize(2));
        self::assertSame([1, 0, 5], [new Criterion('c')->weight, new Criterion('c')->min, new Criterion('c')->max]);
        self::assertSame(Criterion::MAX_WEIGHT, new Criterion('c', Criterion::MAX_WEIGHT, -Criterion::MAX_SCALE, Criterion::MAX_SCALE)->weight);
        self::assertSame(1, new Criterion('c', 1, 0, 1)->max);
    }

    public function testThePolicyHasDefaults(): void
    {
        $policy = new ReviewPolicy();

        self::assertTrue($policy->blind);
        self::assertSame(3, $policy->minReviews);
        self::assertNull($policy->maxReviews);
        self::assertSame(Aggregation::Mean, $policy->aggregation);
        self::assertSame(Visibility::AfterOwnReview, $policy->visibility);
        self::assertTrue($policy->assignedOnly);
        self::assertSame(ReviewPolicy::MAX_REVIEWS, new ReviewPolicy(minReviews: ReviewPolicy::MAX_REVIEWS, maxReviews: ReviewPolicy::MAX_REVIEWS)->maxReviews);
        self::assertSame(1, new ReviewPolicy(minReviews: 1, maxReviews: 1)->maxReviews);
    }

    public function testACampaignFindsItsCriteriaAndRecordsThePhaseItSaw(): void
    {
        $campaign = new Campaign('cfp', Jury::schedule(), [new Criterion('a'), new Criterion('b')]);

        self::assertSame('b', $campaign->criterion('b')?->id);
        self::assertNull($campaign->criterion('z'));
        self::assertSame(Phase::NotStarted, $campaign->observedPhase);

        $seen = $campaign->withObservedPhase(Phase::Review);
        self::assertSame(Phase::Review, $seen->observedPhase);
        self::assertSame([$campaign->id, $campaign->schedule, $campaign->criteria, $campaign->policy], [$seen->id, $seen->schedule, $seen->criteria, $seen->policy]);
        self::assertCount(Campaign::MAX_CRITERIA, new Campaign('c', Jury::schedule(), array_map(static fn (int $i): Criterion => new Criterion('c'.$i), range(1, Campaign::MAX_CRITERIA)))->criteria);
    }

    public function testProposalsConflictsReviewsAndDecisions(): void
    {
        $proposal = new Proposal('cfp', 'talk-1', ['u-1', 'u-2']);
        $other = new Proposal('cfp', 'talk-2', ['u-3']);
        $elsewhere = new Proposal('other', 'talk-1', ['u-1']);

        self::assertTrue($proposal->isAuthoredBy('u-2'));
        self::assertFalse($proposal->isAuthoredBy('u-3'));
        self::assertSame([], new Proposal('cfp', 'p', [])->authors);
        self::assertCount(Proposal::MAX_AUTHORS, new Proposal('cfp', 'p', array_map(static fn (int $i): string => 'u'.$i, range(1, Proposal::MAX_AUTHORS)))->authors);

        $byProposal = ConflictOfInterest::withProposal('cfp', 'r-1', 'talk-1');
        $byAuthor = ConflictOfInterest::withAuthor('cfp', 'r-1', 'u-3');
        self::assertSame(['cfp', 'r-1', 'talk-1', null], [$byProposal->campaign, $byProposal->reviewer, $byProposal->proposal, $byProposal->author]);
        self::assertSame([null, 'u-3'], [$byAuthor->proposal, $byAuthor->author]);
        self::assertTrue($byProposal->concerns($proposal));
        self::assertFalse($byProposal->concerns($other));
        self::assertFalse($byProposal->concerns($elsewhere));
        self::assertTrue($byAuthor->concerns($other));
        self::assertFalse($byAuthor->concerns($proposal));

        $review = new Review('cfp', 'talk-1', 'r-1', [new Score('content', 4)], new DateTimeImmutable('2026-11-12 09:00:00 Europe/Paris'));
        self::assertSame('2026-11-12T08:00:00+00:00', $review->submittedAt->format(\DATE_ATOM));
        self::assertSame(4, $review->scoreFor('content'));
        self::assertNull($review->scoreFor('clarity'));
        self::assertSame(['cfp', 'talk-1', 'r-1'], [$review->campaign, $review->proposal, $review->reviewer]);

        $decision = new DecisionRecord('cfp', 'talk-1', Decision::Accepted, 'chair', new DateTimeImmutable('2026-11-24 10:00:00 America/Toronto'));
        self::assertTrue($decision->isPending());
        self::assertSame('2026-11-24T15:00:00+00:00', $decision->recordedAt->format(\DATE_ATOM));
        $applied = $decision->appliedAt(new DateTimeImmutable('2026-11-25 10:00:00 America/Toronto'));
        self::assertFalse($applied->isPending());
        self::assertSame('2026-11-25T15:00:00+00:00', $applied->appliedAt?->format(\DATE_ATOM));
        self::assertSame(['cfp', 'talk-1', Decision::Accepted, 'chair'], [$applied->campaign, $applied->proposal, $applied->decision, $applied->recordedBy]);
        self::assertSame($decision->recordedAt->format('U.u'), $applied->recordedAt->format('U.u'));

        $standing = new Standing('talk-1', 2, 0.5, true, $decision);
        self::assertSame(['talk-1', 2, 0.5, true, $decision], [$standing->proposal, $standing->reviews, $standing->score, $standing->enoughReviews, $standing->decision]);
    }

    public function testInstantsAreComparedToTheMicrosecond(): void
    {
        self::assertTrue(Utc::before(new DateTimeImmutable('2026-11-10 00:00:00.000001 UTC'), new DateTimeImmutable('2026-11-10 00:00:00.000002 UTC')));
        self::assertFalse(Utc::before(new DateTimeImmutable('2026-11-10 00:00:00 UTC'), new DateTimeImmutable('2026-11-10 00:00:00 UTC')));
    }

    public function testIdentifiersAtTheBoundsAreKept(): void
    {
        self::assertSame(str_repeat('a', 255), Identifier::check(str_repeat('a', 255), 'proposal'));
        self::assertSame('u@local', Identifier::check('u@local', 'author'));
        self::assertSame(['a', 'b'], Identifier::unique(['a', 'b'], 'author'));
    }

    public function testExceptionsBelongToThePackage(): void
    {
        self::assertInstanceOf(ProposalReviewException::class, Refused::because('No.'));
        self::assertSame('No.', Refused::because('No.')->getMessage());
        self::assertSame('There is no campaign "c".', NotFound::campaign('c')->getMessage());
        self::assertSame('Campaign "c" has no proposal "p".', NotFound::proposal('c', 'p')->getMessage());
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'window of no phase' => [static fn (): PhaseWindow => self::window(Phase::Closed, 1, 2), 'A schedule dates the submission, review, author response and deliberation phases only.'];
        yield 'window before start' => [static fn (): PhaseWindow => self::window(Phase::NotStarted, 1, 2), 'A schedule dates the submission, review, author response and deliberation phases only.'];
        yield 'window ending at its start' => [static fn (): PhaseWindow => self::window(Phase::Review, 2, 2), 'The review phase ends after it starts.'];
        yield 'window ending before its start' => [static fn (): PhaseWindow => self::window(Phase::Review, 3, 2), 'The review phase ends after it starts.'];
        yield 'schedule without review' => [static fn (): Schedule => new Schedule([self::window(Phase::Deliberation, 1, 2)]), 'A schedule has a review phase and a deliberation phase.'];
        yield 'schedule without deliberation' => [static fn (): Schedule => new Schedule([self::window(Phase::Review, 1, 2)]), 'A schedule has a review phase and a deliberation phase.'];
        yield 'schedule out of order' => [static fn (): Schedule => new Schedule([self::window(Phase::Deliberation, 1, 2), self::window(Phase::Review, 2, 3)]), 'The phases of a schedule come once each, in their order.'];
        yield 'schedule twice' => [static fn (): Schedule => new Schedule([self::window(Phase::Review, 1, 2), self::window(Phase::Review, 2, 3), self::window(Phase::Deliberation, 3, 4)]), 'The phases of a schedule come once each, in their order.'];
        yield 'schedule with a gap' => [static fn (): Schedule => new Schedule([self::window(Phase::Review, 1, 2), self::window(Phase::Deliberation, 3, 4)]), 'The deliberation phase starts when the review phase ends.'];
        yield 'schedule overlapping' => [static fn (): Schedule => new Schedule([self::window(Phase::Review, 1, 3), self::window(Phase::Deliberation, 2, 4)]), 'The deliberation phase starts when the review phase ends.'];
        yield 'weight zero' => [static fn (): Criterion => new Criterion('c', 0), 'The weight of criterion "c" is 1 to 1000.'];
        yield 'weight too heavy' => [static fn (): Criterion => new Criterion('c', Criterion::MAX_WEIGHT + 1), 'The weight of criterion "c" is 1 to 1000.'];
        yield 'scale flat' => [static fn (): Criterion => new Criterion('c', 1, 3, 3), 'The scale of criterion "c" runs upward within -1000 to 1000.'];
        yield 'scale too low' => [static fn (): Criterion => new Criterion('c', 1, -Criterion::MAX_SCALE - 1, 3), 'The scale of criterion "c" runs upward within -1000 to 1000.'];
        yield 'scale too high' => [static fn (): Criterion => new Criterion('c', 1, 0, Criterion::MAX_SCALE + 1), 'The scale of criterion "c" runs upward within -1000 to 1000.'];
        yield 'criterion e-mail' => [static fn (): Criterion => new Criterion('a@b.example'), 'Criterion identifiers are stable opaque identifiers, never e-mail addresses.'];
        yield 'score criterion empty' => [static fn (): Score => new Score('', 1), 'Criterion identifiers are 1 to 255 bytes without control characters.'];
        yield 'no review needed' => [static fn (): ReviewPolicy => new ReviewPolicy(minReviews: 0), 'A proposal needs 1 to 1000 reviews.'];
        yield 'too many reviews needed' => [static fn (): ReviewPolicy => new ReviewPolicy(minReviews: ReviewPolicy::MAX_REVIEWS + 1), 'A proposal needs 1 to 1000 reviews.'];
        yield 'maximum under minimum' => [static fn (): ReviewPolicy => new ReviewPolicy(minReviews: 3, maxReviews: 2), 'The most reviews of a proposal is its minimum to 1000.'];
        yield 'maximum too high' => [static fn (): ReviewPolicy => new ReviewPolicy(maxReviews: ReviewPolicy::MAX_REVIEWS + 1), 'The most reviews of a proposal is its minimum to 1000.'];
        yield 'campaign without criteria' => [static fn (): Campaign => new Campaign('c', Jury::schedule(), []), 'A campaign has 1 to 50 criteria.'];
        yield 'campaign with too many criteria' => [static fn (): Campaign => new Campaign('c', Jury::schedule(), array_map(static fn (int $i): Criterion => new Criterion('c'.$i), range(0, Campaign::MAX_CRITERIA))), 'A campaign has 1 to 50 criteria.'];
        yield 'criterion twice' => [static fn (): Campaign => new Campaign('c', Jury::schedule(), [new Criterion('a'), new Criterion('a')]), 'Criterion identifiers are given once each.'];
        yield 'campaign identifier' => [static fn (): Campaign => new Campaign("c\t", Jury::schedule(), [new Criterion('a')]), 'Campaign identifiers are 1 to 255 bytes without control characters.'];
        yield 'author twice' => [static fn (): Proposal => new Proposal('c', 'p', ['u', 'u']), 'Author identifiers are given once each.'];
        yield 'author e-mail' => [static fn (): Proposal => new Proposal('c', 'p', ['u@mail.example.org']), 'Author identifiers are stable opaque identifiers, never e-mail addresses.'];
        yield 'too many authors' => [static fn (): Proposal => new Proposal('c', 'p', array_map(static fn (int $i): string => 'u'.$i, range(0, Proposal::MAX_AUTHORS))), 'A proposal has at most 50 authors.'];
        yield 'proposal identifier' => [static fn (): Proposal => new Proposal('c', str_repeat('p', 256), []), 'Proposal identifiers are 1 to 255 bytes without control characters.'];
        yield 'reviewer e-mail' => [static fn (): ConflictOfInterest => ConflictOfInterest::withProposal('c', 'r@x.example', 'p'), 'Reviewer identifiers are stable opaque identifiers, never e-mail addresses.'];
        yield 'conflict author empty' => [static fn (): ConflictOfInterest => ConflictOfInterest::withAuthor('c', 'r', ''), 'Author identifiers are 1 to 255 bytes without control characters.'];
        yield 'review reviewer' => [static fn (): Review => new Review('c', 'p', "r\x7F", [], new DateTimeImmutable()), 'Reviewer identifiers are 1 to 255 bytes without control characters.'];
        yield 'decision member' => [static fn (): DecisionRecord => new DecisionRecord('c', 'p', Decision::Rejected, 'chair@x.example', new DateTimeImmutable()), 'Member identifiers are stable opaque identifiers, never e-mail addresses.'];
    }

    /**
     * @param Closure(): mixed $build
     */
    #[DataProvider('invalidValues')]
    public function testInvalidValuesAreRefused(Closure $build, string $message): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIs($message);

        $build();
    }

    private static function window(Phase $phase, int $startDay, int $endDay): PhaseWindow
    {
        return new PhaseWindow($phase, new DateTimeImmutable(\sprintf('2026-11-%02d 00:00:00 UTC', $startDay)), new DateTimeImmutable(\sprintf('2026-11-%02d 00:00:00 UTC', $endDay)));
    }
}
