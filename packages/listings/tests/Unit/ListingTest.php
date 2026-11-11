<?php

declare(strict_types=1);

namespace Phil\Listings\Tests\Unit;

use DateTimeImmutable;
use Phil\Listings\Criteria;
use Phil\Listings\Criterion;
use Phil\Listings\Exception\ForbiddenTransition;
use Phil\Listings\Exception\InvalidListing;
use Phil\Listings\KindPolicy;
use Phil\Listings\Listing;
use Phil\Listings\ListingId;
use Phil\Listings\ListingKind;
use Phil\Listings\ListingStatus;
use Phil\Listings\Location;
use Phil\Listings\MemberId;
use Phil\Listings\Moderation;
use Phil\Listings\ModerationReason;
use Phil\Listings\Title;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Listing::class)]
#[CoversClass(ForbiddenTransition::class)]
final class ListingTest extends TestCase
{
    private const string START = '2026-01-01T00:00:00+00:00';

    public function testADraftIsSeenByItsAuthorOnly(): void
    {
        $listing = $this->draft($this->after());
        $at = $this->moment(0);

        self::assertSame('l1', $listing->id->value);
        self::assertSame('partner', $listing->kind->value);
        self::assertSame('author', $listing->author->value);
        self::assertSame('Two players wanted', $listing->title->value);
        self::assertNotNull($listing->criteria->get('players'));
        self::assertSame('Plateau', $listing->location->area);
        self::assertNull($listing->minimumAge);
        self::assertSame(ListingStatus::Draft, $listing->status);
        self::assertSame(self::START, $listing->createdAt->format(\DATE_ATOM));
        self::assertNull($listing->publishedAt);
        self::assertNull($listing->expiresAt);
        self::assertSame(0, $listing->extensions);
        self::assertNull($listing->remindedAt);
        self::assertSame([], $listing->reporters);
        self::assertNull($listing->moderationReason);
        self::assertFalse($listing->isVisibleAt($at));
    }

    #[TestWith([null, null, null])]
    #[TestWith([null, 16, 16])]
    #[TestWith([13, null, 13])]
    #[TestWith([13, 18, 18])]
    #[TestWith([18, 13, 18])]
    #[TestWith([0, 0, 0])]
    #[TestWith([null, 0, 0])]
    #[TestWith([0, null, 0])]
    #[TestWith([null, 120, 120])]
    public function testTheMinimumAgeIsTheOlderOfTheListingsAndTheKinds(?int $kind, ?int $listing, ?int $kept): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'), minimumAge: $kind);

        self::assertSame($kept, $this->draft($policy, $listing)->minimumAge);
    }

    #[TestWith([-1])]
    #[TestWith([121])]
    public function testAMinimumAgeOutOfBoundsIsRefused(int $age): void
    {
        $this->expectExceptionObject(InvalidListing::because(\sprintf('A minimum age is from 0 to 120; %d is not.', $age)));

        $this->draft($this->after(), $age);
    }

    public function testAnUnmoderatedKindPublishesOnSubmission(): void
    {
        $policy = $this->after();
        $listing = $this->draft($policy)->submit($policy, $this->moment(3600));

        self::assertSame(ListingStatus::Published, $listing->status);
        self::assertSame('2026-01-01T01:00:00+00:00', $listing->publishedAt?->format(\DATE_ATOM));
        self::assertSame('2026-01-31T01:00:00+00:00', $listing->expiresAt?->format(\DATE_ATOM));
        self::assertTrue($listing->isVisibleAt($this->moment(3600)));
        self::assertTrue($listing->isVisibleAt($this->day(30)));
        self::assertFalse($listing->isVisibleAt($this->day(30)->modify('+1 hour')));
    }

    public function testAModeratedKindWaitsForApproval(): void
    {
        $policy = $this->before();
        $pending = $this->draft($policy)->submit($policy, $this->moment(0));

        self::assertSame(ListingStatus::PendingReview, $pending->status);
        self::assertNull($pending->expiresAt);
        self::assertFalse($pending->isVisibleAt($this->moment(0)));

        $published = $pending->approve($policy, $this->day(2));

        self::assertSame(ListingStatus::Published, $published->status);
        self::assertSame('2026-01-03T00:00:00+00:00', $published->publishedAt?->format(\DATE_ATOM));
        self::assertSame('2026-02-02T00:00:00+00:00', $published->expiresAt?->format(\DATE_ATOM));
    }

    public function testARejectedListingIsEditedAndSubmittedAgain(): void
    {
        $policy = $this->before();
        $rejected = $this->draft($policy)->submit($policy, $this->moment(0))->reject($policy, ModerationReason::of('off_topic'));

        self::assertSame(ListingStatus::Rejected, $rejected->status);
        self::assertSame('off_topic', $rejected->moderationReason?->value);

        $edited = $rejected->edit($policy, Title::of('Better title'), Criteria::of(), Location::online(), null);

        self::assertSame(ListingStatus::Rejected, $edited->status);
        self::assertSame('Better title', $edited->title->value);

        $approved = $edited->submit($policy, $this->moment(0))->approve($policy, $this->moment(0));

        self::assertSame(ListingStatus::Published, $approved->status);
        self::assertNull($approved->moderationReason);
    }

    public function testEditingAModeratedPublishedListingSendsItBackToReviewWithItsExpiry(): void
    {
        $policy = $this->before();
        $published = $this->draft($policy)->submit($policy, $this->moment(0))->approve($policy, $this->moment(0));
        $edited = $published->edit($policy, Title::of('New title'), Criteria::of(), Location::area('Verdun', '1 rue X'), 21);

        self::assertSame(ListingStatus::PendingReview, $edited->status);
        self::assertSame('Verdun', $edited->location->area);
        self::assertSame(21, $edited->minimumAge);

        $approved = $edited->approve($policy, $this->day(5));

        self::assertSame(self::START, $approved->publishedAt?->format(\DATE_ATOM));
        self::assertSame('2026-01-31T00:00:00+00:00', $approved->expiresAt?->format(\DATE_ATOM));
    }

    public function testEditingAnUnmoderatedPublishedListingKeepsItPublished(): void
    {
        $policy = $this->after();
        $published = $this->draft($policy)->submit($policy, $this->moment(0));
        $edited = $published->edit($policy, Title::of('New title'), Criteria::of([Criterion::flag('remote', true)]), Location::online(), null);

        self::assertSame(ListingStatus::Published, $edited->status);
        self::assertTrue($edited->location->isOnline());
        self::assertNotNull($edited->criteria->get('remote'));
        self::assertSame($published->expiresAt, $edited->expiresAt);
        self::assertSame($published->createdAt, $edited->createdAt);
        self::assertSame('author', $edited->author->value);
    }

    public function testAnEditKeepsEveryOtherField(): void
    {
        $policy = $this->after();
        $listing = $this->published($policy)->report($policy, MemberId::of('r1'), $this->moment(0))->extend($policy, $this->moment(0))->markReminded($this->day(1));
        $edited = $listing->edit($policy, Title::of('t'), Criteria::of(), Location::online(), null);

        self::assertSame($listing->publishedAt, $edited->publishedAt);
        self::assertSame(1, $edited->extensions);
        self::assertSame($listing->remindedAt, $edited->remindedAt);
        self::assertCount(1, $edited->reporters);
        self::assertSame('l1', $edited->id->value);
        self::assertSame('partner', $edited->kind->value);
    }

    public function testEnoughDistinctReportsHideAListingUntilAModeratorDecides(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'), reportsToHide: 2);
        $listing = $this->published($policy);

        $once = $listing->report($policy, MemberId::of('r1'), $this->moment(0));
        self::assertSame(ListingStatus::Published, $once->status);
        self::assertSame(['r1'], array_map(static fn (MemberId $member): string => $member->value, $once->reporters));

        self::assertSame($once, $once->report($policy, MemberId::of('r1'), $this->moment(0)));

        $hidden = $once->report($policy, MemberId::of('r2'), $this->moment(0));
        self::assertSame(ListingStatus::Hidden, $hidden->status);
        self::assertFalse($hidden->isVisibleAt($this->moment(0)));
        self::assertSame($listing->expiresAt, $hidden->expiresAt);
        self::assertSame($listing->publishedAt, $hidden->publishedAt);

        $restored = $hidden->approve($policy, $this->day(3));
        self::assertSame(ListingStatus::Published, $restored->status);
        self::assertSame([], $restored->reporters);
        self::assertSame($listing->expiresAt, $restored->expiresAt);

        $removed = $hidden->remove($policy, ModerationReason::of('unsafe'));
        self::assertSame(ListingStatus::Removed, $removed->status);
        self::assertSame('unsafe', $removed->moderationReason?->value);
    }

    public function testAHiddenListingRestoredAfterItsExpiryGetsAFreshOne(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'), reportsToHide: 1);
        $hidden = $this->published($policy)->markReminded($this->day(28))->report($policy, MemberId::of('r1'), $this->day(1));
        $restored = $hidden->approve($policy, $this->day(40));

        self::assertSame(self::START, $restored->publishedAt?->format(\DATE_ATOM));
        self::assertSame('2026-03-12T00:00:00+00:00', $restored->expiresAt?->format(\DATE_ATOM));
        self::assertNull($restored->remindedAt);
        self::assertSame('2026-03-02T00:00:00+00:00', $hidden->approve($policy, $this->day(30))->expiresAt?->format(\DATE_ATOM));
    }

    public function testRestoringBeforeExpiryKeepsTheReminder(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'), reportsToHide: 1);
        $hidden = $this->published($policy)->markReminded($this->day(28))->report($policy, MemberId::of('r1'), $this->day(28));

        self::assertSame('2026-01-29T00:00:00+00:00', $hidden->approve($policy, $this->day(29))->remindedAt?->format(\DATE_ATOM));
    }

    public function testTheAuthorCannotReportTheirOwnListing(): void
    {
        $policy = $this->after();

        $this->expectExceptionObject(ForbiddenTransition::because('Only a visible listing can be reported, and never by its author.'));

        $this->published($policy)->report($policy, MemberId::of('author'), $this->moment(0));
    }

    public function testAnExpiredListingCannotBeReported(): void
    {
        $policy = $this->after();

        $this->expectException(ForbiddenTransition::class);

        $this->published($policy)->report($policy, MemberId::of('r1'), $this->day(30));
    }

    public function testAPublishedListingExpiresWithTime(): void
    {
        $policy = $this->after();
        $published = $this->published($policy);

        self::assertSame($published, $published->at($this->day(30)->modify('-1 second')));

        $expired = $published->at($this->day(30));
        self::assertSame(ListingStatus::Expired, $expired->status);
        self::assertSame($published->expiresAt, $expired->expiresAt);

        $draft = $this->draft($policy);
        self::assertSame($draft, $draft->at($this->day(90)));
    }

    public function testAnExtensionCountsFromTheLaterOfExpiryAndNow(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'), extensionDays: 10, maxExtensions: 2);
        $published = $this->published($policy)->markReminded($this->day(27));

        $early = $published->extend($policy, $this->day(27));
        self::assertSame('2026-02-10T00:00:00+00:00', $early->expiresAt?->format(\DATE_ATOM));
        self::assertSame(1, $early->extensions);
        self::assertNull($early->remindedAt);
        self::assertSame(ListingStatus::Published, $early->status);

        $late = $published->at($this->day(45))->extend($policy, $this->day(45));
        self::assertSame(ListingStatus::Published, $late->status);
        self::assertSame('2026-02-25T00:00:00+00:00', $late->expiresAt?->format(\DATE_ATOM));

        $atExpiry = $published->extend($policy, $this->day(30));
        self::assertSame('2026-02-10T00:00:00+00:00', $atExpiry->expiresAt?->format(\DATE_ATOM));

        $twice = $early->extend($policy, $this->day(28));
        self::assertSame(2, $twice->extensions);

        $this->expectExceptionObject(ForbiddenTransition::because('A listing of the kind "partner" is extended at most 2 times.'));

        $twice->extend($policy, $this->day(28));
    }

    public function testTheReminderIsDueWithinItsDaysOncePerExtension(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'), reminderDays: 3, maxExtensions: 1);
        $published = $this->published($policy);

        self::assertFalse($published->needsReminderAt($policy, $this->day(27)->modify('-1 second')));
        self::assertTrue($published->needsReminderAt($policy, $this->day(27)));
        self::assertTrue($published->needsReminderAt($policy, $this->day(30)->modify('-1 second')));
        self::assertFalse($published->needsReminderAt($policy, $this->day(30)));

        $reminded = $published->markReminded($this->day(27));
        self::assertSame('2026-01-28T00:00:00+00:00', $reminded->remindedAt?->format(\DATE_ATOM));
        self::assertFalse($reminded->needsReminderAt($policy, $this->day(28)));

        $extended = $reminded->extend($policy, $this->day(28));
        self::assertFalse($extended->needsReminderAt($policy, $this->day(58)), 'No extension is left to remind of.');
    }

    public function testNoReminderIsDueWithoutReminderDays(): void
    {
        $policy = KindPolicy::of(ListingKind::of('partner'), reminderDays: 0);

        self::assertFalse($this->published($policy)->needsReminderAt($policy, $this->day(30)->modify('-1 second')));
    }

    public function testOnlyAPublishedListingIsReminded(): void
    {
        $this->expectExceptionObject(ForbiddenTransition::because('Only a published listing has a reminder to send.'));

        $this->draft($this->after())->markReminded($this->moment(0));
    }

    public function testTheAuthorClosesTheListing(): void
    {
        $policy = $this->after();
        $closed = $this->published($policy)->close($policy);

        self::assertSame(ListingStatus::Closed, $closed->status);
        self::assertFalse($closed->isVisibleAt($this->moment(0)));

        $this->expectExceptionObject(ForbiddenTransition::because('A listing that is closed cannot be closed.'));

        $closed->close($policy);
    }

    public function testAClosedListingKeepsItsModerationReason(): void
    {
        $policy = $this->before();
        $closed = $this->draft($policy)->submit($policy, $this->moment(0))->reject($policy, ModerationReason::of('spam'))->close($policy);

        self::assertSame('spam', $closed->moderationReason?->value);
        self::assertSame('spam', $closed->at($this->day(1))->moderationReason?->value);
    }

    public function testAnExpiredListingKeepsItsFields(): void
    {
        $policy = $this->after();
        $expired = $this->published($policy)->report($policy, MemberId::of('r1'), $this->moment(0))->at($this->day(31));

        self::assertCount(1, $expired->reporters);
        self::assertSame(self::START, $expired->publishedAt?->format(\DATE_ATOM));
    }

    /**
     * @return iterable<string, array{ListingStatus, string}>
     */
    public static function forbiddenTransitions(): iterable
    {
        yield 'submit a published listing' => [ListingStatus::Published, 'submit'];
        yield 'approve a draft' => [ListingStatus::Draft, 'approve'];
        yield 'reject a published listing' => [ListingStatus::Published, 'reject'];
        yield 'remove a draft' => [ListingStatus::Draft, 'remove'];
        yield 'remove a rejected listing' => [ListingStatus::Rejected, 'remove'];
        yield 'remove a closed listing' => [ListingStatus::Closed, 'remove'];
        yield 'report a hidden listing' => [ListingStatus::Hidden, 'report'];
        yield 'edit a hidden listing' => [ListingStatus::Hidden, 'edit'];
        yield 'edit a closed listing' => [ListingStatus::Closed, 'edit'];
        yield 'edit a removed listing' => [ListingStatus::Removed, 'edit'];
        yield 'extend a draft' => [ListingStatus::Draft, 'extend'];
        yield 'extend a hidden listing' => [ListingStatus::Hidden, 'extend'];
        yield 'close a removed listing' => [ListingStatus::Removed, 'close'];
    }

    #[DataProvider('forbiddenTransitions')]
    public function testATransitionItsStatusDoesNotAllowIsRefused(ListingStatus $status, string $transition): void
    {
        $policy = $this->after();
        $listing = $this->restored($status);

        $this->expectException(ForbiddenTransition::class);

        match ($transition) {
            'submit' => $listing->submit($policy, $this->moment(0)),
            'approve' => $listing->approve($policy, $this->moment(0)),
            'reject' => $listing->reject($policy, ModerationReason::of('spam')),
            'remove' => $listing->remove($policy, ModerationReason::of('spam')),
            'report' => $listing->report($policy, MemberId::of('r1'), $this->moment(0)),
            'edit' => $listing->edit($policy, Title::of('t'), Criteria::of(), Location::online(), null),
            'extend' => $listing->extend($policy, $this->moment(0)),
            default => $listing->close($policy),
        };
    }

    /**
     * @return iterable<string, array{ListingStatus, string, ListingStatus}>
     */
    public static function allowedTransitions(): iterable
    {
        yield 'remove a pending listing' => [ListingStatus::PendingReview, 'remove', ListingStatus::Removed];
        yield 'remove a published listing' => [ListingStatus::Published, 'remove', ListingStatus::Removed];
        yield 'remove an expired listing' => [ListingStatus::Expired, 'remove', ListingStatus::Removed];
        yield 'edit a draft' => [ListingStatus::Draft, 'edit', ListingStatus::Draft];
        yield 'edit a pending listing' => [ListingStatus::PendingReview, 'edit', ListingStatus::PendingReview];
        yield 'edit an expired listing' => [ListingStatus::Expired, 'edit', ListingStatus::Expired];
        yield 'close a draft' => [ListingStatus::Draft, 'close', ListingStatus::Closed];
        yield 'close a pending listing' => [ListingStatus::PendingReview, 'close', ListingStatus::Closed];
        yield 'close a rejected listing' => [ListingStatus::Rejected, 'close', ListingStatus::Closed];
        yield 'close a hidden listing' => [ListingStatus::Hidden, 'close', ListingStatus::Closed];
        yield 'close an expired listing' => [ListingStatus::Expired, 'close', ListingStatus::Closed];
    }

    #[DataProvider('allowedTransitions')]
    public function testATransitionItsStatusAllowsIsMade(ListingStatus $status, string $transition, ListingStatus $after): void
    {
        $policy = $this->before();
        $listing = $this->restored($status);

        $changed = match ($transition) {
            'remove' => $listing->remove($policy, ModerationReason::of('spam')),
            'edit' => $listing->edit($policy, Title::of('t'), Criteria::of(), Location::online(), null),
            default => $listing->close($policy),
        };

        self::assertSame($after, $changed->status);
    }

    public function testATransitionNamesTheStatusAndTheChange(): void
    {
        $this->expectExceptionObject(ForbiddenTransition::because('A listing that is draft cannot be approved.'));

        $this->draft($this->after())->approve($this->after(), $this->moment(0));
    }

    public function testAReportUnderAPolicyOfAnotherKindIsRefused(): void
    {
        $this->expectException(InvalidListing::class);

        $this->published($this->after())->report(KindPolicy::of(ListingKind::of('job')), MemberId::of('r1'), $this->moment(0));
    }

    public function testAPolicyOfAnotherKindIsRefused(): void
    {
        $this->expectExceptionObject(InvalidListing::because('The listing is of the kind "partner", not "job".'));

        $this->draft($this->after())->submit(KindPolicy::of(ListingKind::of('job')), $this->moment(0));
    }

    public function testTheExactPlaceIsShownToTheAuthorAndToAcceptedRespondents(): void
    {
        $listing = $this->draft($this->after());

        self::assertSame('4200 Saint-Laurent', $listing->locationFor(MemberId::of('author'), false)->exact);
        self::assertSame('4200 Saint-Laurent', $listing->locationFor(MemberId::of('someone'), true)->exact);
        self::assertNull($listing->locationFor(MemberId::of('someone'), false)->exact);
        self::assertSame('Plateau', $listing->locationFor(MemberId::of('someone'), false)->area);
    }

    public function testTheAgeIsAdmittedFromTheMinimum(): void
    {
        $open = $this->draft($this->after());
        $adults = $this->draft($this->after(), 18);

        self::assertTrue($open->admitsAge(null));
        self::assertTrue($open->admitsAge(12));
        self::assertFalse($adults->admitsAge(null));
        self::assertFalse($adults->admitsAge(17));
        self::assertTrue($adults->admitsAge(18));
    }

    public function testAStoredListingIsRestored(): void
    {
        $at = $this->moment(0);
        $listing = Listing::restore(ListingId::of('l9'), ListingKind::of('job'), MemberId::of('a'), Title::of('t'), Criteria::of(), Location::online(), 16, ListingStatus::Hidden, $at, $at, $this->day(30), 2, $at, [MemberId::of('r1')], ModerationReason::of('spam'));

        self::assertSame(ListingStatus::Hidden, $listing->status);
        self::assertSame(16, $listing->minimumAge);
        self::assertSame(2, $listing->extensions);
        self::assertSame($at, $listing->remindedAt);
        self::assertSame('spam', $listing->moderationReason?->value);
        self::assertSame(0, Listing::restore(ListingId::of('l9'), ListingKind::of('job'), MemberId::of('a'), Title::of('t'), Criteria::of(), Location::online(), null, ListingStatus::Draft, $at, null, null, 0, null, array_fill(0, 100, MemberId::of('r')), null)->extensions);
    }

    #[TestWith([-1, 0])]
    #[TestWith([0, 101])]
    public function testAnImpossibleStoredListingIsRefused(int $extensions, int $reporters): void
    {
        $this->expectExceptionObject(InvalidListing::because('A stored listing has no negative extension count and at most 100 reporters.'));

        Listing::restore(ListingId::of('l9'), ListingKind::of('job'), MemberId::of('a'), Title::of('t'), Criteria::of(), Location::online(), null, ListingStatus::Draft, $this->moment(0), null, null, $extensions, null, array_fill(0, $reporters, MemberId::of('r')), null);
    }

    private function after(): KindPolicy
    {
        return KindPolicy::of(ListingKind::of('partner'));
    }

    private function before(): KindPolicy
    {
        return KindPolicy::of(ListingKind::of('partner'), moderation: Moderation::BeforePublication);
    }

    private function draft(KindPolicy $policy, ?int $minimumAge = null): Listing
    {
        return Listing::draft(ListingId::of('l1'), $policy, MemberId::of('author'), Title::of('Two players wanted'), Criteria::of([Criterion::range('players', 2, 2)]), Location::area('Plateau', '4200 Saint-Laurent'), $minimumAge, $this->moment(0));
    }

    private function published(KindPolicy $policy): Listing
    {
        return $this->draft($policy)->submit($policy, $this->moment(0));
    }

    private function restored(ListingStatus $status): Listing
    {
        $at = $this->moment(0);

        return Listing::restore(ListingId::of('l1'), ListingKind::of('partner'), MemberId::of('author'), Title::of('t'), Criteria::of(), Location::online(), null, $status, $at, $at, $this->day(30), 0, null, [], null);
    }

    private function moment(int $seconds): DateTimeImmutable
    {
        return new DateTimeImmutable(self::START)->modify(\sprintf('+%d seconds', $seconds));
    }

    private function day(int $days): DateTimeImmutable
    {
        return new DateTimeImmutable(self::START)->modify(\sprintf('+%d days', $days));
    }
}
