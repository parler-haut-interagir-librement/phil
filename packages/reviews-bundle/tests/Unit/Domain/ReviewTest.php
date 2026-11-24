<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Exception\ReviewRefused;
use Phil\ReviewsBundle\Domain\Model\Review;
use Phil\ReviewsBundle\Domain\Status\ContestOutcome;
use Phil\ReviewsBundle\Domain\Status\Origin;
use Phil\ReviewsBundle\Domain\Status\ReviewStatus;
use Phil\ReviewsBundle\Domain\Status\Verification;
use Phil\ReviewsBundle\Domain\ValueObject\Compensation;
use Phil\ReviewsBundle\Domain\ValueObject\Contest;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\Response;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Review::class)]
final class ReviewTest extends TestCase
{
    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->at = new DateTimeImmutable('2026-11-21 10:00:00 UTC');
    }

    public function testAWrittenReview(): void
    {
        $review = $this->review();

        self::assertSame(ReviewStatus::Published, $review->status());
        self::assertTrue($review->isPublished());
        self::assertFalse($review->isHeld());
        self::assertSame(Origin::Site, $review->origin);
        self::assertSame(Verification::Purchase, $review->verification);
        self::assertSame('ada', $review->author()?->value);
        self::assertTrue($review->isBy(MemberId::fromString('ada')));
        self::assertFalse($review->isBy(MemberId::fromString('bob')));
        self::assertNull($review->editedAt());
        self::assertNull($review->removalCriterion());
        self::assertNull($review->withdrawnAt());
        self::assertNull($review->response());
        self::assertNull($review->contest());
        self::assertSame(0, $review->helpful());
        self::assertSame(0, $review->unhelpful());
        self::assertSame($this->at, $review->writtenAt);
    }

    public function testAReconstitutedReviewKeepsItsState(): void
    {
        $contest = new Contest('Evidence', $this->at);
        $response = new Response('Thanks', null, $this->at);
        $review = Review::reconstitute(
            ReviewId::generate(),
            SubjectRef::of('game', 'catan'),
            null,
            new ReviewContent(3),
            Compensation::received(),
            Origin::Transferred,
            null,
            $this->at,
            $this->at,
            ReviewStatus::Withdrawn,
            'spam',
            $this->at,
            $response,
            $contest,
            -2,
            7,
        );
        $none = Review::reconstitute(ReviewId::generate(), SubjectRef::of('game', 'catan'), null, new ReviewContent(3), Compensation::none(), Origin::Site, null, $this->at, null, ReviewStatus::Published, null, null, null, null, 4, -2);
        self::assertSame([4, 0], [$none->helpful(), $none->unhelpful()]);

        self::assertNull($review->author());
        self::assertSame('spam', $review->removalCriterion());
        self::assertSame($contest, $review->contest());
        self::assertSame($response, $review->response());
        self::assertSame(0, $review->helpful());
        self::assertSame(7, $review->unhelpful());
        self::assertSame(Origin::Transferred, $review->origin);
        self::assertFalse($review->isBy(MemberId::fromString('ada')));

        $this->expectException(InvalidReview::class);
        Review::reconstitute(ReviewId::generate(), SubjectRef::of('game', 'catan'), null, new ReviewContent(3), Compensation::none(), Origin::Site, null, $this->at, null, ReviewStatus::Withdrawn, 'Bad', null, null, null, 0, 0);
    }

    public function testTheAuthorEdits(): void
    {
        $review = $this->review();
        $review->edit(new ReviewContent(5), Compensation::received(), $this->at->modify('+1 hour'));

        self::assertSame(5, $review->content()->overall);
        self::assertTrue($review->compensation()->received);
        self::assertEquals($this->at->modify('+1 hour'), $review->editedAt());
    }

    public function testAWithdrawnReviewIsNotEdited(): void
    {
        $review = $this->review();
        $review->withdraw('spam', $this->at);

        $this->expectExceptionObject(new ReviewRefused('Only a published review of yours can be changed.'));
        $review->edit(new ReviewContent(5), Compensation::none(), $this->at);
    }

    public function testAnAnonymisedReviewIsNotEdited(): void
    {
        $review = $this->review();
        $review->anonymise();

        $this->expectException(ReviewRefused::class);
        $review->edit(new ReviewContent(5), Compensation::none(), $this->at);
    }

    public function testAHeldReviewGetsItsCriterionLater(): void
    {
        $review = $this->review();
        $review->withdraw(null, $this->at);

        self::assertTrue($review->isHeld());
        self::assertSame(ReviewStatus::Withdrawn, $review->status());
        self::assertSame($this->at, $review->withdrawnAt());

        $review->withdraw('spam', $this->at->modify('+1 day'));
        self::assertFalse($review->isHeld());
        self::assertSame('spam', $review->removalCriterion());
        self::assertSame($this->at, $review->withdrawnAt());

        $review->withdraw(null, $this->at->modify('+2 days'));
        self::assertSame('spam', $review->removalCriterion());
    }

    public function testAMalformedCriterionIsRefused(): void
    {
        $this->expectException(InvalidReview::class);
        $this->review()->withdraw('Spam!', $this->at);
    }

    public function testAContestedWithdrawalIsUpheldOrRejected(): void
    {
        $review = $this->review();
        $review->withdraw('spam', $this->at);
        $review->contestWithdrawal(new Contest('Evidence', $this->at));

        try {
            $review->contestWithdrawal(new Contest('Again', $this->at));
            self::fail('A withdrawal was contested twice.');
        } catch (ReviewRefused $reviewRefused) {
            self::assertSame('This withdrawal was contested already.', $reviewRefused->getMessage());
        }

        $review->settleContest(ContestOutcome::Rejected, $this->at);
        self::assertSame(ReviewStatus::Withdrawn, $review->status());

        try {
            $review->settleContest(ContestOutcome::Upheld, $this->at);
            self::fail('A settled contest was settled again.');
        } catch (ReviewRefused $reviewRefused) {
            self::assertSame('No contest of this review is open.', $reviewRefused->getMessage());
        }

        $review->publish();
        self::assertNull($review->removalCriterion());
        self::assertNull($review->withdrawnAt());
        self::assertSame(ContestOutcome::Rejected, $review->contest()?->outcome);

        $review->withdraw('off-topic', $this->at);
        self::assertNull($review->contest());
        $review->contestWithdrawal(new Contest('New evidence', $this->at));
        $review->settleContest(ContestOutcome::Upheld, $this->at);
        self::assertTrue($review->isPublished());
    }

    public function testOnlyAWithdrawalUnderACriterionIsContested(): void
    {
        $review = $this->review();

        try {
            $review->contestWithdrawal(new Contest('Evidence', $this->at));
            self::fail('A published review was contested.');
        } catch (ReviewRefused $reviewRefused) {
            self::assertSame('Only a review of yours withdrawn under a criterion can be contested.', $reviewRefused->getMessage());
        }

        $review->withdraw(null, $this->at);

        try {
            $review->contestWithdrawal(new Contest('Evidence', $this->at));
            self::fail('A held review was contested.');
        } catch (ReviewRefused) {
            self::addToAssertionCount(1);
        }

        $review->withdraw('spam', $this->at);
        $review->anonymise();
        $this->expectException(ReviewRefused::class);
        $review->contestWithdrawal(new Contest('Evidence', $this->at));
    }

    public function testNoContestIsSettledWithoutOne(): void
    {
        $this->expectException(ReviewRefused::class);
        $this->review()->settleContest(ContestOutcome::Upheld, $this->at);
    }

    public function testAResponseIsGivenAndRemoved(): void
    {
        $review = $this->review();
        $response = new Response('Thanks', MemberId::fromString('shop'), $this->at);
        $review->respond($response);
        self::assertSame($response, $review->response());
        $review->removeResponse();
        self::assertNull($review->response());
    }

    public function testAnonymisingKeepsTheScores(): void
    {
        $review = Review::write(
            ReviewId::generate(),
            SubjectRef::of('game', 'catan'),
            MemberId::fromString('ada'),
            new ReviewContent(4, ['rules' => 2], 'Title', 'Body', ['quick']),
            Compensation::received('A free copy'),
            $this->at,
        );
        $review->withdraw('spam', $this->at);
        $review->contestWithdrawal(new Contest('Evidence', $this->at));
        $review->anonymise();

        self::assertNull($review->author());
        self::assertNull($review->content()->title);
        self::assertNull($review->content()->body);
        self::assertSame(4, $review->content()->overall);
        self::assertSame(['rules' => 2], $review->content()->criteria);
        self::assertSame(['quick'], $review->content()->superlatives);
        self::assertTrue($review->compensation()->received);
        self::assertNull($review->compensation()->note);
        self::assertNull($review->contest());
        self::assertNull($review->verification);
    }

    private function review(): Review
    {
        return Review::write(
            ReviewId::generate(),
            SubjectRef::of('game', 'catan'),
            MemberId::fromString('ada'),
            new ReviewContent(3),
            Compensation::none(),
            $this->at,
            Verification::Purchase,
        );
    }
}
