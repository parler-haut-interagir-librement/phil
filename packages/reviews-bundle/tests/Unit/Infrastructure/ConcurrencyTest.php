<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use Phil\ReviewsBundle\Application\Reviews;
use Phil\ReviewsBundle\Domain\Exception\DuplicateReview;
use Phil\ReviewsBundle\Domain\Exception\ReviewRefused;
use Phil\ReviewsBundle\Domain\Model\Review;
use Phil\ReviewsBundle\Domain\ValueObject\Compensation;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Phil\ReviewsBundle\Infrastructure\Doctrine\Columns;
use Phil\ReviewsBundle\Infrastructure\Doctrine\DbalRatingRepository;
use Phil\ReviewsBundle\Infrastructure\Doctrine\DbalReviewRepository;
use Phil\ReviewsBundle\Tests\Unit\Fixture\StaleReviews;
use Phil\ReviewsBundle\Tests\Unit\Fixture\Store;
use Phil\ReviewsBundle\Tests\Unit\Fixture\UnchangedRowsConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Two requests at the same moment, as two connections to one SQLite file: the unique constraints decide who writes a
 * review or a vote, and the ratings computed in any order come to the same.
 */
#[CoversClass(DbalReviewRepository::class)]
#[CoversClass(DbalRatingRepository::class)]
#[CoversClass(Reviews::class)]
final class ConcurrencyTest extends TestCase
{
    private string $path;

    private Store $first;

    private Store $second;

    #[Override]
    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phil-reviews-');
        self::assertIsString($path);
        $this->path = $path;
        Store::create($this->connection());
        $this->first = new Store($this->connection());
        $second = $this->connection();
        $this->second = new Store($second, new StaleReviews(new DbalReviewRepository($second)), $this->first->clock);
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink($this->path);
    }

    public function testTwoRequestsWritingOneReviewLeaveOne(): void
    {
        $this->first->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(4));

        try {
            $this->second->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(1));
            self::fail('The second request wrote a second review.');
        } catch (ReviewRefused $reviewRefused) {
            self::assertSame('You reviewed this subject already: change your review instead.', $reviewRefused->getMessage());
            self::assertInstanceOf(DuplicateReview::class, $reviewRefused->getPrevious());
        }

        self::assertSame(1, $this->second->reviews->count(Store::game()));
        self::assertSame(4, $this->second->ratings->summary(Store::game())->sum);
    }

    public function testTwoVotesOfOneMemberLeaveOne(): void
    {
        $review = $this->first->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(4));

        $this->first->reviews->vote($review->id, Store::member('bob'), true);
        $this->second->reviews->vote($review->id, Store::member('bob'), false);
        $this->second->reviews->vote($review->id, Store::member('cy'), true);

        self::assertSame(2, Columns::int($this->first->connection->fetchOne('SELECT COUNT(*) FROM phil_review_vote')));
        self::assertSame(1, $this->first->reviews->get($review->id)?->helpful());
        self::assertSame(1, $this->first->reviews->get($review->id)->unhelpful());
        self::assertFalse($this->first->reviews->voteOf($review->id, Store::member('bob')));
    }

    public function testAVoteInsideATransactionOfTheCallerLeavesItUsable(): void
    {
        $review = $this->first->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(4));
        $this->first->reviews->vote($review->id, Store::member('bob'), true);

        $this->first->connection->transactional(function () use ($review): void {
            $this->first->reviews->vote($review->id, Store::member('bob'), false);
            $this->first->reviews->vote($review->id, Store::member('cy'), false);
        });

        self::assertSame(2, $this->first->reviews->get($review->id)?->unhelpful());
        self::assertSame(0, $this->first->reviews->get($review->id)->helpful());
    }

    public function testTheRatingsComputedInAnyOrderComeToTheSame(): void
    {
        $this->first->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(4, ['rules' => 2]));
        // Written by the second request, whose computation comes after the first one's.
        $this->second->repository->add(Review::write(ReviewId::generate(), Store::game(), Store::member('bob'), new ReviewContent(2, ['rules' => 4]), Compensation::none(), $this->first->clock->now()));
        $first = $this->first->ratings->recompute(Store::game());
        $second = $this->second->ratings->recompute(Store::game());

        self::assertSame(2, $first->count);
        self::assertSame(6, $first->sum);
        self::assertSame(3.0, $first->criteria['rules']->mean);
        self::assertEquals($first, $second);
        self::assertEquals($first, $this->first->ratings->recompute(Store::game()));
        self::assertSame(4, Columns::int($this->first->connection->fetchOne('SELECT version FROM phil_review_subject')));
    }

    public function testAComputationTakesARowThatAnotherCreatedMeanwhile(): void
    {
        $unchanged = new Store(UnchangedRowsConnection::on($this->path), clock: $this->first->clock);

        $unchanged->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(4));
        $unchanged->reviews->write(Store::game(), Store::member('bob'), new ReviewContent(2));
        $unchanged->ratings->suspend(Store::game(), true);

        $summary = $unchanged->ratings->summary(Store::game());
        self::assertSame(2, $summary->count);
        self::assertSame(6, $summary->sum);
        self::assertTrue($summary->suspended);
        self::assertSame(1, Columns::int($unchanged->connection->fetchOne('SELECT COUNT(*) FROM phil_review_subject')));
        // Created by the first computation; the second one and the suspension each update it, are told of no row, try an
        // insert the key refuses, and update it again.
        self::assertSame(5, Columns::int($unchanged->connection->fetchOne('SELECT version FROM phil_review_subject')));
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->path]);
    }
}
