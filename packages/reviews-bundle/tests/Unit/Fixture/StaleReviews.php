<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\ReviewsBundle\Domain\Contract\ReviewRepository;
use Phil\ReviewsBundle\Domain\Model\Review;
use Phil\ReviewsBundle\Domain\Status\ReviewOrder;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * A store read before another request wrote: it never finds the member's review of a subject, as a request that checked
 * before the other one committed; everything else goes to the store given.
 */
final readonly class StaleReviews implements ReviewRepository
{
    public function __construct(
        private ReviewRepository $inner,
    ) {
    }

    #[Override]
    public function get(ReviewId $id): ?Review
    {
        return $this->inner->get($id);
    }

    #[Override]
    public function of(SubjectRef $subject, MemberId $author): ?Review
    {
        return null;
    }

    #[Override]
    public function add(Review $review): void
    {
        $this->inner->add($review);
    }

    #[Override]
    public function save(Review $review): void
    {
        $this->inner->save($review);
    }

    #[Override]
    public function published(SubjectRef $subject, ReviewOrder $order, int $limit, int $offset = 0): array
    {
        return $this->inner->published($subject, $order, $limit, $offset);
    }

    #[Override]
    public function countPublished(SubjectRef $subject): int
    {
        return $this->inner->countPublished($subject);
    }

    #[Override]
    public function vote(ReviewId $review, MemberId $voter, bool $helpful, DateTimeImmutable $at): void
    {
        $this->inner->vote($review, $voter, $helpful, $at);
    }

    #[Override]
    public function retractVote(ReviewId $review, MemberId $voter): void
    {
        $this->inner->retractVote($review, $voter);
    }

    #[Override]
    public function voteOf(ReviewId $review, MemberId $voter): ?bool
    {
        return $this->inner->voteOf($review, $voter);
    }

    #[Override]
    public function by(MemberId $author): array
    {
        return $this->inner->by($author);
    }

    #[Override]
    public function votesBy(MemberId $voter): array
    {
        return $this->inner->votesBy($voter);
    }

    #[Override]
    public function respondedBy(MemberId $responder): array
    {
        return $this->inner->respondedBy($responder);
    }

    #[Override]
    public function forgetVotes(MemberId $voter): array
    {
        return $this->inner->forgetVotes($voter);
    }

    #[Override]
    public function forgetResponses(MemberId $responder): array
    {
        return $this->inner->forgetResponses($responder);
    }

    #[Override]
    public function remove(ReviewId $id): void
    {
        $this->inner->remove($id);
    }
}
