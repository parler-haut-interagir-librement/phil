<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\ReviewsBundle\Application\Event\ContestFiled;
use Phil\ReviewsBundle\Application\Event\ReviewPublished;
use Phil\ReviewsBundle\Application\Event\ReviewWithdrawn;
use Phil\ReviewsBundle\Application\Event\ReviewWritten;
use Phil\ReviewsBundle\Application\Ratings;
use Phil\ReviewsBundle\Application\RemovalCriteria;
use Phil\ReviewsBundle\Application\Reviews;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Exception\ReviewRefused;
use Phil\ReviewsBundle\Domain\Model\Review;
use Phil\ReviewsBundle\Domain\Status\ContestOutcome;
use Phil\ReviewsBundle\Domain\Status\ReviewOrder;
use Phil\ReviewsBundle\Domain\Status\ReviewStatus;
use Phil\ReviewsBundle\Domain\Status\Verification;
use Phil\ReviewsBundle\Domain\ValueObject\Compensation;
use Phil\ReviewsBundle\Domain\ValueObject\Participation;
use Phil\ReviewsBundle\Domain\ValueObject\RatingSummary;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use Phil\ReviewsBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reviews::class)]
#[CoversClass(Ratings::class)]
final class ReviewsTest extends TestCase
{
    private Store $store;

    protected function setUp(): void
    {
        $this->store = new Store();
    }

    public function testAMemberWritesAReviewThatIsPublishedAndCounted(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(4, ['rules' => 5], 'Great fun.', ['quick']));

        self::assertSame(ReviewStatus::Published, $review->status());
        self::assertNull($review->verification);
        self::assertFalse($review->compensation()->received);
        self::assertEquals(new DateTimeImmutable('2026-11-21 10:00:00 UTC'), $review->writtenAt);
        self::assertSame(1, $this->store->reviews->count(Store::game()));
        self::assertSame(4, $this->store->ratings->summary(Store::game())->sum);
        self::assertEquals([new ReviewWritten($review->id->value, 'game', 'catan')], $this->store->events->events);
        self::assertSame('Great fun.', $this->store->reviews->get($review->id)?->content()->body);
        self::assertSame($review->id->value, $this->store->reviews->mineOf(Store::game(), Store::member('ada'))?->id->value);
    }

    public function testASecondReviewOfOneSubjectIsRefused(): void
    {
        $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(4));

        $this->expectExceptionObject(new ReviewRefused('You reviewed this subject already: change your review instead.'));
        $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(2));
    }

    public function testTheRefusalOfASecondReviewComesBeforeTheStore(): void
    {
        $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(4));

        try {
            $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(2));
            self::fail('A second review was taken.');
        } catch (ReviewRefused $reviewRefused) {
            self::assertNull($reviewRefused->getPrevious());
            self::assertSame(1, $this->store->reviews->count(Store::game()));
        }
    }

    public function testEveryChangeWorksWithoutAnEventDispatcher(): void
    {
        $reviews = new Reviews($this->store->repository, $this->store->ratings, $this->store->types, new RemovalCriteria(), $this->store->participations, $this->store->responders, $this->store->clock);
        $ada = Store::member('ada');

        $review = $reviews->write(Store::game(), $ada, Store::content(4));
        $reviews->edit($review->id, $ada, Store::content(5));
        $reviews->withdraw($review->id, 'off-topic');
        $reviews->contest($review->id, $ada, 'Evidence');
        $reviews->settleContest($review->id, true);
        $reviews->hold($review->id);
        $reviews->publish($review->id);

        self::assertTrue($reviews->get($review->id)?->isPublished());
        self::assertSame([], $this->store->events->events);
    }

    public function testAnotherSubjectOrMemberIsAnotherReview(): void
    {
        $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(4));
        $this->store->reviews->write(Store::game('carcassonne'), Store::member('ada'), Store::content(3));
        $this->store->reviews->write(Store::game(), Store::member('bob'), Store::content(2));

        self::assertSame(2, $this->store->reviews->count(Store::game()));
        self::assertSame(1, $this->store->reviews->count(Store::game('carcassonne')));
    }

    public function testTheContentIsCheckedAgainstTheType(): void
    {
        $this->expectExceptionObject(new InvalidReview('"luck" is not a criterion of "game".'));
        $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(4, ['luck' => 2]));
    }

    public function testAnUndeclaredTypeIsRefused(): void
    {
        $this->expectException(InvalidReview::class);
        $this->store->reviews->write(SubjectRef::of('book', 'dune'), Store::member('ada'), Store::content(4));
    }

    public function testASuspendedSubjectTakesNoReviewAndKeepsItsReviews(): void
    {
        $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(4));
        $this->store->ratings->suspend(Store::game(), true);

        self::assertSame('suspended', $this->store->reviews->refusal(Store::game(), Store::member('bob')));
        self::assertSame(1, $this->store->reviews->count(Store::game()));
        self::assertSame(1, $this->store->ratings->summary(Store::game())->count);

        try {
            $this->store->reviews->write(Store::game(), Store::member('bob'), Store::content(1));
            self::fail('A suspended subject took a review.');
        } catch (ReviewRefused $reviewRefused) {
            self::assertSame('The reviews of this subject are suspended for now; the reviews given stay.', $reviewRefused->getMessage());
        }

        $mine = $this->store->reviews->mineOf(Store::game(), Store::member('ada'));
        self::assertNotNull($mine);

        try {
            $this->store->reviews->edit($mine->id, Store::member('ada'), Store::content(1));
            self::fail('A review of a suspended subject was changed.');
        } catch (ReviewRefused $refused) {
            self::assertSame('The reviews of this subject are suspended for now; the reviews given stay.', $refused->getMessage());
        }

        self::assertSame(4, $this->store->ratings->summary(Store::game())->sum);
        $this->store->ratings->suspend(Store::game(), false);
        $this->store->reviews->write(Store::game(), Store::member('bob'), Store::content(1));
        self::assertSame(2, $this->store->ratings->summary(Store::game())->count);
        self::assertNull($this->store->reviews->refusal(Store::game(), Store::member('cy')));
    }

    public function testSuspendingAnUndeclaredTypeIsRefused(): void
    {
        $this->expectException(InvalidReview::class);
        $this->store->ratings->suspend(SubjectRef::of('book', 'dune'), true);
    }

    public function testAParticipantReviewsWithinTheWindowAndIsMarked(): void
    {
        $event = SubjectRef::of('event', 'gameday');
        $this->store->participations->set($event, Store::member('ada'), new Participation(Verification::Attendance, new DateTimeImmutable('2026-10-22 10:00:00 UTC')));

        self::assertNull($this->store->reviews->refusal($event, Store::member('ada')));
        $review = $this->store->reviews->write($event, Store::member('ada'), Store::content(5));

        self::assertSame(Verification::Attendance, $review->verification);
        self::assertSame(Verification::Attendance, $this->store->reviews->get($review->id)?->verification);
    }

    public function testTheWindowClosesTheSecondAfterItsLastDay(): void
    {
        $event = SubjectRef::of('event', 'gameday');
        $this->store->participations->set($event, Store::member('ada'), new Participation(Verification::Attendance, new DateTimeImmutable('2026-10-22 09:59:59 UTC')));

        self::assertSame('window', $this->store->reviews->refusal($event, Store::member('ada')));
        $this->expectExceptionObject(new ReviewRefused('The reviews of this subject are taken within 30 days of taking part.'));
        $this->store->reviews->write($event, Store::member('ada'), Store::content(5));
    }

    public function testAParticipationToComeIsRefused(): void
    {
        $event = SubjectRef::of('event', 'gameday');
        $this->store->participations->set($event, Store::member('ada'), new Participation(Verification::Purchase, new DateTimeImmutable('2026-11-21 10:00:01 UTC')));

        $this->expectExceptionObject(new ReviewRefused('Your participation has not happened yet: review it afterwards.'));
        $this->store->reviews->write($event, Store::member('ada'), Store::content(5));
    }

    public function testAParticipationOfThisVeryMomentIsAccepted(): void
    {
        $event = SubjectRef::of('event', 'gameday');
        $this->store->participations->set($event, Store::member('ada'), new Participation(Verification::Purchase, new DateTimeImmutable('2026-11-21 10:00:00 UTC')));

        self::assertSame(Verification::Purchase, $this->store->reviews->write($event, Store::member('ada'), Store::content(5))->verification);
    }

    public function testANonParticipantIsRefusedWhereOnlyParticipantsReview(): void
    {
        $event = SubjectRef::of('event', 'gameday');

        self::assertSame('window', $this->store->reviews->refusal($event, Store::member('ada')));
        $this->expectExceptionObject(new ReviewRefused('Only those who took part review this subject.'));
        $this->store->reviews->write($event, Store::member('ada'), Store::content(5));
    }

    public function testAParticipantOutsideTheWindowIsStillMarkedWhereEveryoneReviews(): void
    {
        $this->store->participations->set(Store::game(), Store::member('ada'), new Participation(Verification::Purchase, new DateTimeImmutable('2020-01-01 UTC')));

        self::assertSame(Verification::Purchase, $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(5))->verification);
    }

    public function testAnUnknownTypeIsAReasonOfRefusal(): void
    {
        self::assertSame('unknown', $this->store->reviews->refusal(SubjectRef::of('book', 'dune'), Store::member('ada')));
    }

    public function testACompensationIsKeptWithTheReview(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(5), Compensation::received('A free copy'));

        $kept = $this->store->reviews->get($review->id);
        self::assertTrue($kept?->compensation()->received);
        self::assertSame('A free copy', $kept->compensation()->note);
    }

    public function testTheAuthorEditsTheirReview(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(2, ['rules' => 1], 'Meh', ['quick']));
        $this->store->clock->modify('+1 hour');
        $edited = $this->store->reviews->edit($review->id, Store::member('ada'), Store::content(4, ['replayability' => 4], 'Better', ['family', 'gorgeous']), Compensation::received());

        self::assertEquals(new DateTimeImmutable('2026-11-21 11:00:00 UTC'), $edited->editedAt());
        $kept = $this->store->reviews->get($review->id);
        self::assertSame(['replayability' => 4], $kept?->content()->criteria);
        self::assertSame(['family', 'gorgeous'], $kept->content()->superlatives);
        self::assertTrue($kept->compensation()->received);
        $summary = $this->store->ratings->summary(Store::game());
        self::assertSame(4, $summary->sum);
        self::assertSame(['family' => 1, 'gorgeous' => 1], $summary->superlatives);
        self::assertCount(2, $this->store->events->of(ReviewWritten::class));
    }

    public function testOnlyTheAuthorEdits(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(2));

        $this->expectExceptionObject(new ReviewRefused('Only the author changes a review.'));
        $this->store->reviews->edit($review->id, Store::member('bob'), Store::content(5));
    }

    public function testAnEditIsCheckedAgainstTheType(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(2));

        $this->expectException(InvalidReview::class);
        $this->store->reviews->edit($review->id, Store::member('ada'), Store::content(6));
    }

    public function testAMemberVotesOnceAndChangesTheirVote(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(4));

        $this->store->reviews->vote($review->id, Store::member('bob'), true);
        $this->store->reviews->vote($review->id, Store::member('bob'), true);
        $this->store->reviews->vote($review->id, Store::member('cy'), false);
        self::assertSame([1, 1], $this->counts($review->id));
        self::assertTrue($this->store->reviews->voteOf($review->id, Store::member('bob')));
        self::assertFalse($this->store->reviews->voteOf($review->id, Store::member('cy')));

        $this->store->reviews->vote($review->id, Store::member('bob'), false);
        self::assertSame([0, 2], $this->counts($review->id));

        $this->store->reviews->retractVote($review->id, Store::member('bob'));
        self::assertSame([0, 1], $this->counts($review->id));
        self::assertNull($this->store->reviews->voteOf($review->id, Store::member('bob')));
    }

    public function testAMemberDoesNotVoteOnTheirOwnReview(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(4));

        $this->expectExceptionObject(new ReviewRefused('Your own review takes no vote of yours.'));
        $this->store->reviews->vote($review->id, Store::member('ada'), true);
    }

    public function testAWithdrawnReviewTakesNoVote(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(4));
        $this->store->reviews->withdraw($review->id, 'spam');

        try {
            $this->store->reviews->vote($review->id, Store::member('bob'), true);
            self::fail('A withdrawn review took a vote.');
        } catch (ReviewRefused $reviewRefused) {
            self::assertSame('This review is not published.', $reviewRefused->getMessage());
        }

        $this->expectException(ReviewRefused::class);
        $this->store->reviews->retractVote($review->id, Store::member('bob'));
    }

    public function testAResponderRespondsAndRemovesTheResponse(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(2));
        $this->store->responders->members = ['shop'];

        self::assertTrue($this->store->reviews->mayRespond(Store::game(), Store::member('shop')));
        self::assertFalse($this->store->reviews->mayRespond(Store::game(), null));
        self::assertFalse($this->store->reviews->mayRespond(Store::game(), Store::member('bob')));

        $this->store->reviews->respond($review->id, Store::member('shop'), ' Thank you, we fixed the box. ');
        $response = $this->store->reviews->get($review->id)?->response();
        self::assertSame('Thank you, we fixed the box.', $response?->text);
        self::assertSame('shop', $response->responder?->value);
        self::assertEquals(new DateTimeImmutable('2026-11-21 10:00:00 UTC'), $response->at);

        $this->store->reviews->removeResponse($review->id, Store::member('shop'));
        self::assertNull($this->store->reviews->get($review->id)?->response());
    }

    public function testOnlyAResponderResponds(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(2));

        $this->expectExceptionObject(new ReviewRefused('You do not answer the reviews of this subject.'));
        $this->store->reviews->respond($review->id, Store::member('bob'), 'Hi');
    }

    public function testOnlyAResponderRemovesAResponse(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(2));

        $this->expectExceptionObject(new ReviewRefused('You do not answer the reviews of this subject.'));
        $this->store->reviews->removeResponse($review->id, Store::member('bob'));
    }

    public function testAWithdrawalNamesAPublishedCriterionAndLeavesTheRatings(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(1));
        $this->store->clock->modify('+1 day');
        $withdrawn = $this->store->reviews->withdraw($review->id, 'off-topic');

        self::assertSame('off-topic', $withdrawn->removalCriterion());
        self::assertEquals(new DateTimeImmutable('2026-11-22 10:00:00 UTC'), $this->store->reviews->get($review->id)?->withdrawnAt());
        self::assertSame(0, $this->store->ratings->summary(Store::game())->count);
        self::assertSame(0, $this->store->reviews->count(Store::game()));
        self::assertEquals([new ReviewWithdrawn($review->id->value, 'game', 'catan')], $this->store->events->of(ReviewWithdrawn::class));
    }

    public function testNoReviewIsWithdrawnOutsideThePublishedCriteria(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(1));

        $this->expectExceptionObject(new ReviewRefused('"too-negative" is not a published criterion: no review is withdrawn outside them.'));
        $this->store->reviews->withdraw($review->id, 'too-negative');
    }

    public function testAnUnknownReviewIsRefused(): void
    {
        $this->expectExceptionObject(new ReviewRefused('This review does not exist.'));
        $this->store->reviews->withdraw(ReviewId::generate(), 'spam');
    }

    public function testAHeldReviewIsPublishedAgain(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(3));
        $held = $this->store->reviews->hold($review->id);

        self::assertTrue($held->isHeld());
        self::assertSame(0, $this->store->ratings->summary(Store::game())->count);

        $this->store->reviews->publish($review->id);
        self::assertTrue($this->store->reviews->get($review->id)?->isPublished());
        self::assertSame(1, $this->store->ratings->summary(Store::game())->count);
        self::assertCount(1, $this->store->events->of(ReviewPublished::class));

        $this->store->reviews->publish($review->id);
        self::assertCount(1, $this->store->events->of(ReviewPublished::class));
    }

    public function testTheAuthorContestsAWithdrawalThatIsUpheld(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(1, [], 'The box was broken.'));
        $this->store->reviews->withdraw($review->id, 'off-topic');
        $this->store->reviews->contest($review->id, Store::member('ada'), 'The broken box is part of the product.');

        self::assertTrue($this->store->reviews->get($review->id)?->contest()?->isOpen());
        self::assertEquals([new ContestFiled($review->id->value, 'game', 'catan')], $this->store->events->of(ContestFiled::class));

        $this->store->clock->modify('+2 days');
        $settled = $this->store->reviews->settleContest($review->id, true);

        self::assertSame(ContestOutcome::Upheld, $settled->contest()?->outcome);
        $kept = $this->store->reviews->get($review->id);
        self::assertTrue($kept?->isPublished());
        self::assertEquals(new DateTimeImmutable('2026-11-23 10:00:00 UTC'), $kept->contest()?->settledAt);
        self::assertSame(1, $this->store->ratings->summary(Store::game())->count);
        self::assertCount(1, $this->store->events->of(ReviewPublished::class));
    }

    public function testARejectedContestKeepsTheWithdrawal(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(1));
        $this->store->reviews->withdraw($review->id, 'spam');
        $this->store->reviews->contest($review->id, Store::member('ada'), 'It is not spam.');
        $this->store->reviews->settleContest($review->id, false);

        $kept = $this->store->reviews->get($review->id);
        self::assertSame(ContestOutcome::Rejected, $kept?->contest()?->outcome);
        self::assertSame(ReviewStatus::Withdrawn, $kept->status());
        self::assertSame(0, $this->store->ratings->summary(Store::game())->count);
        self::assertSame([], $this->store->events->of(ReviewPublished::class));
    }

    public function testOnlyTheAuthorContests(): void
    {
        $review = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(1));
        $this->store->reviews->withdraw($review->id, 'spam');

        $this->expectExceptionObject(new ReviewRefused('Only the author contests the withdrawal of a review.'));
        $this->store->reviews->contest($review->id, Store::member('bob'), 'Evidence');
    }

    public function testTheReviewsAreListedInEachOrder(): void
    {
        $low = $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(1));
        $this->store->clock->modify('+1 minute');
        $high = $this->store->reviews->write(Store::game(), Store::member('bob'), Store::content(5));
        $this->store->clock->modify('+1 minute');
        $middle = $this->store->reviews->write(Store::game(), Store::member('cy'), Store::content(3));
        $this->store->reviews->vote($low->id, Store::member('bob'), true);

        self::assertSame([$low->id->value, $middle->id->value, $high->id->value], $this->ids(ReviewOrder::Helpful));
        self::assertSame([$middle->id->value, $high->id->value, $low->id->value], $this->ids(ReviewOrder::Newest));
        self::assertSame([$high->id->value, $middle->id->value, $low->id->value], $this->ids(ReviewOrder::Highest));
        self::assertSame([$low->id->value, $middle->id->value, $high->id->value], $this->ids(ReviewOrder::Lowest));
        self::assertSame([$high->id->value], array_map(static fn (Review $review): string => $review->id->value, $this->store->reviews->of(Store::game(), ReviewOrder::Newest, 1, 1)));
    }

    public function testTheRankingIsByWeightedAverage(): void
    {
        $this->store->reviews->write(Store::game('a'), Store::member('ada'), Store::content(5));
        $this->store->reviews->write(Store::game('b'), Store::member('ada'), Store::content(4));
        $this->store->reviews->write(Store::game('b'), Store::member('bob'), Store::content(4));
        $this->store->reviews->write(Store::game('b'), Store::member('cy'), Store::content(5));

        // a: (3 × 2 + 5) / 3 ≈ 3.67; b: (3 × 2 + 13) / 5 = 3.8.
        self::assertSame(['b'], array_map(static fn (RatingSummary $summary): string => $summary->subject->id, $this->store->ratings->ranking('game', 1)));
        self::assertSame(['b', 'a'], array_map(static fn (RatingSummary $summary): string => $summary->subject->id, $this->store->ratings->ranking('game', 5)));
    }

    public function testTheRankingOfAnUndeclaredTypeIsRefused(): void
    {
        $this->expectException(InvalidReview::class);
        $this->store->ratings->ranking('book', 3);
    }

    public function testEverySubjectIsComputedAgain(): void
    {
        $this->store->reviews->write(Store::game('a'), Store::member('ada'), Store::content(5));
        $this->store->reviews->write(Store::game('b'), Store::member('ada'), Store::content(4));
        $this->store->reviews->write(SubjectRef::of('product', 'mug'), Store::member('ada'), Store::content(9));
        $this->store->connection->executeStatement('UPDATE phil_review_subject SET review_count = 0, rating_sum = 0');

        self::assertSame(2, $this->store->ratings->recomputeAll('game'));
        self::assertSame(5, $this->store->ratings->summary(Store::game('a'))->sum);
        self::assertSame(0, $this->store->ratings->summary(SubjectRef::of('product', 'mug'))->sum);
        self::assertSame(3, $this->store->ratings->recomputeAll());
        self::assertSame(9, $this->store->ratings->summary(SubjectRef::of('product', 'mug'))->sum);
    }

    public function testComputingAnUndeclaredTypeIsRefused(): void
    {
        $this->expectException(InvalidReview::class);
        $this->store->ratings->recomputeAll('book');
    }

    /**
     * @return array{int, int}
     */
    private function counts(ReviewId $id): array
    {
        $review = $this->store->reviews->get($id);

        return [$review?->helpful() ?? -1, $review?->unhelpful() ?? -1];
    }

    /**
     * @return list<string>
     */
    private function ids(ReviewOrder $order): array
    {
        return array_map(static fn (Review $review): string => $review->id->value, $this->store->reviews->of(Store::game(), $order, 10));
    }
}
