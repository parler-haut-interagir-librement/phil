<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\ReviewsBundle\Domain\Exception\DuplicateReview;
use Phil\ReviewsBundle\Domain\Model\Review;
use Phil\ReviewsBundle\Domain\Status\ReviewOrder;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * Where the reviews and their votes of helpfulness are kept. A member reviews a subject once and votes on a review
 * once: the store's unique constraints decide, so that two requests at the same moment cannot break either rule.
 */
interface ReviewRepository
{
    public function get(ReviewId $id): ?Review;

    /**
     * The review the member wrote of the subject, whatever its state.
     */
    public function of(SubjectRef $subject, MemberId $author): ?Review;

    /**
     * @throws DuplicateReview when the author already reviewed the subject
     */
    public function add(Review $review): void;

    public function save(Review $review): void;

    /**
     * The published reviews of the subject, in the order asked.
     *
     * @param positive-int $limit
     * @param int<0, max>  $offset
     *
     * @return list<Review>
     */
    public function published(SubjectRef $subject, ReviewOrder $order, int $limit, int $offset = 0): array;

    public function countPublished(SubjectRef $subject): int;

    /**
     * Records the member's vote, or changes it; the counts of the review are written again from the votes.
     */
    public function vote(ReviewId $review, MemberId $voter, bool $helpful, DateTimeImmutable $at): void;

    /**
     * Removes the member's vote, if any; the counts of the review are written again from the votes.
     */
    public function retractVote(ReviewId $review, MemberId $voter): void;

    /**
     * The member's vote on the review: `true` helpful, `false` not helpful, `null` none.
     */
    public function voteOf(ReviewId $review, MemberId $voter): ?bool;

    /**
     * The reviews the member wrote, whatever their state.
     *
     * @return list<Review>
     */
    public function by(MemberId $author): array;

    /**
     * The member's votes.
     *
     * @return list<array{review: ReviewId, helpful: bool, at: DateTimeImmutable}>
     */
    public function votesBy(MemberId $voter): array;

    /**
     * The reviews the member responded to.
     *
     * @return list<Review>
     */
    public function respondedBy(MemberId $responder): array;

    /**
     * Removes the member's votes, the counts of the reviews written again; the reviews' identifiers.
     *
     * @return list<ReviewId>
     */
    public function forgetVotes(MemberId $voter): array;

    /**
     * Removes the responses the member wrote; the subjects of the reviews.
     *
     * @return list<SubjectRef>
     */
    public function forgetResponses(MemberId $responder): array;

    /**
     * Removes the review, its scores, superlatives and votes.
     */
    public function remove(ReviewId $id): void;
}
