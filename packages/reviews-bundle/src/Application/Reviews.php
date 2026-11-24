<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application;

use DateTimeImmutable;
use Phil\ReviewsBundle\Application\Contract\Participations;
use Phil\ReviewsBundle\Application\Contract\ResponderPolicy;
use Phil\ReviewsBundle\Application\Event\ContestFiled;
use Phil\ReviewsBundle\Application\Event\ReviewPublished;
use Phil\ReviewsBundle\Application\Event\ReviewWithdrawn;
use Phil\ReviewsBundle\Application\Event\ReviewWritten;
use Phil\ReviewsBundle\Domain\Contract\ReviewRepository;
use Phil\ReviewsBundle\Domain\Exception\DuplicateReview;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Exception\ReviewRefused;
use Phil\ReviewsBundle\Domain\Model\Review;
use Phil\ReviewsBundle\Domain\Status\ContestOutcome;
use Phil\ReviewsBundle\Domain\Status\ReviewOrder;
use Phil\ReviewsBundle\Domain\ValueObject\Compensation;
use Phil\ReviewsBundle\Domain\ValueObject\Contest;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\Participation;
use Phil\ReviewsBundle\Domain\ValueObject\Response;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The entry point of the reviews: a member writes one review of a subject and changes it, votes on the helpfulness of
 * the others, the merchant or the organiser responds publicly, the moderation withdraws a review under a published
 * criterion and publishes it again, the author contests a withdrawal, and the reviews of a subject are suspended and
 * resumed. Every change that touches the ratings computes them again.
 */
final readonly class Reviews
{
    public function __construct(
        private ReviewRepository $reviews,
        private Ratings $ratings,
        private SubjectTypes $types,
        private RemovalCriteria $criteria,
        private Participations $participations,
        private ResponderPolicy $responders,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * The member reviews the subject.
     *
     * @throws InvalidReview when the type is not declared or the content does not fit it
     * @throws ReviewRefused when the reviews of the subject are suspended, the member is not a participant in its
     *                       window, or already reviewed it
     */
    public function write(SubjectRef $subject, MemberId $author, ReviewContent $content, ?Compensation $compensation = null): Review
    {
        $compensation ??= Compensation::none();
        $type = $this->types->get($subject->type);
        $type->check($content);

        if ($this->ratings->summary($subject)->suspended) {
            throw $this->suspended();
        }

        $now = $this->clock->now();
        $participation = $this->participations->of($subject, $author);

        if ($type->participantsOnly) {
            $this->checkWindow($participation, $type->window, $now);
        }

        if ($this->reviews->of($subject, $author) instanceof Review) {
            throw $this->already();
        }

        $review = Review::write(ReviewId::generate(), $subject, $author, $content, $compensation, $now, $participation?->kind);

        try {
            $this->reviews->add($review);
        } catch (DuplicateReview $duplicateReview) {
            throw $this->already($duplicateReview);
        }

        $this->ratings->recompute($subject);
        $this->events?->dispatch(new ReviewWritten($review->id->value, $subject->type, $subject->id));

        return $review;
    }

    /**
     * The author changes their review.
     *
     * @throws InvalidReview when the content does not fit the type
     * @throws ReviewRefused when the review is not the author's, is withdrawn, or the reviews of its subject are suspended
     */
    public function edit(ReviewId $id, MemberId $author, ReviewContent $content, ?Compensation $compensation = null): Review
    {
        $compensation ??= Compensation::none();
        $review = $this->mine($id, $author);
        $this->types->get($review->subject->type)->check($content);

        if ($this->ratings->summary($review->subject)->suspended) {
            throw $this->suspended();
        }

        $review->edit($content, $compensation, $this->clock->now());
        $this->reviews->save($review);
        $this->ratings->recompute($review->subject);
        $this->events?->dispatch(new ReviewWritten($review->id->value, $review->subject->type, $review->subject->id));

        return $review;
    }

    /**
     * The member says whether the review helped them, once: a second vote replaces the first.
     *
     * @throws ReviewRefused when the review is not published, or is the member's own
     */
    public function vote(ReviewId $id, MemberId $voter, bool $helpful): void
    {
        $review = $this->published($id);

        if ($review->isBy($voter)) {
            throw ReviewRefused::because('Your own review takes no vote of yours.');
        }

        $this->reviews->vote($id, $voter, $helpful, $this->clock->now());
    }

    /**
     * The member takes their vote back.
     *
     * @throws ReviewRefused when the review is not published
     */
    public function retractVote(ReviewId $id, MemberId $voter): void
    {
        $this->published($id);
        $this->reviews->retractVote($id, $voter);
    }

    /**
     * The merchant or the organiser responds publicly to a published review; a second response replaces the first.
     *
     * @throws InvalidReview when the text is empty or too long
     * @throws ReviewRefused when the review is not published, or the member does not answer the reviews of its subject
     */
    public function respond(ReviewId $id, MemberId $responder, string $text): Review
    {
        $review = $this->published($id);

        if (!$this->responders->mayRespond($review->subject, $responder)) {
            throw ReviewRefused::because('You do not answer the reviews of this subject.');
        }

        $review->respond(new Response($text, $responder, $this->clock->now()));
        $this->reviews->save($review);

        return $review;
    }

    /**
     * @throws ReviewRefused when the review does not exist, or the member does not answer the reviews of its subject
     */
    public function removeResponse(ReviewId $id, MemberId $responder): Review
    {
        $review = $this->find($id);

        if (!$this->responders->mayRespond($review->subject, $responder)) {
            throw ReviewRefused::because('You do not answer the reviews of this subject.');
        }

        $review->removeResponse();
        $this->reviews->save($review);

        return $review;
    }

    /**
     * Withdraws the review under a published criterion.
     *
     * @throws ReviewRefused when the review does not exist, or the criterion is not published
     */
    public function withdraw(ReviewId $id, string $criterion): Review
    {
        if (!$this->criteria->has($criterion)) {
            throw ReviewRefused::because(\sprintf('"%s" is not a published criterion: no review is withdrawn outside them.', $criterion));
        }

        return $this->withdrawn($this->find($id), $criterion);
    }

    /**
     * Holds the review out of sight while the moderation looks at it, without a criterion yet.
     *
     * @throws ReviewRefused when the review does not exist
     */
    public function hold(ReviewId $id): Review
    {
        return $this->withdrawn($this->find($id), null);
    }

    /**
     * Publishes a withdrawn review again.
     *
     * @throws ReviewRefused when the review does not exist
     */
    public function publish(ReviewId $id): Review
    {
        $review = $this->find($id);

        if ($review->isPublished()) {
            return $review;
        }

        $review->publish();
        $this->reviews->save($review);
        $this->ratings->recompute($review->subject);
        $this->events?->dispatch(new ReviewPublished($review->id->value, $review->subject->type, $review->subject->id));

        return $review;
    }

    /**
     * The author contests the withdrawal of their review, with their evidence.
     *
     * @throws InvalidReview when the evidence is empty or too long
     * @throws ReviewRefused when the review is not the author's, is not withdrawn under a criterion, or was contested already
     */
    public function contest(ReviewId $id, MemberId $author, string $evidence): Review
    {
        $review = $this->find($id);

        if (!$review->isBy($author)) {
            throw ReviewRefused::because('Only the author contests the withdrawal of a review.');
        }

        $review->contestWithdrawal(new Contest($evidence, $this->clock->now()));
        $this->reviews->save($review);
        $this->events?->dispatch(new ContestFiled($review->id->value, $review->subject->type, $review->subject->id));

        return $review;
    }

    /**
     * Decides the open contest: upheld, the review is published again.
     *
     * @throws ReviewRefused when the review does not exist or no contest of it is open
     */
    public function settleContest(ReviewId $id, bool $upheld): Review
    {
        $review = $this->find($id);
        $review->settleContest($upheld ? ContestOutcome::Upheld : ContestOutcome::Rejected, $this->clock->now());

        $this->reviews->save($review);

        if ($upheld) {
            $this->ratings->recompute($review->subject);
            $this->events?->dispatch(new ReviewPublished($review->id->value, $review->subject->type, $review->subject->id));
        }

        return $review;
    }

    public function get(ReviewId $id): ?Review
    {
        return $this->reviews->get($id);
    }

    /**
     * The member's review of the subject, whatever its state.
     */
    public function mineOf(SubjectRef $subject, MemberId $member): ?Review
    {
        return $this->reviews->of($subject, $member);
    }

    /**
     * The published reviews of the subject, in the order asked; none is filtered out.
     *
     * @param positive-int $limit
     * @param int<0, max>  $offset
     *
     * @return list<Review>
     */
    public function of(SubjectRef $subject, ReviewOrder $order, int $limit, int $offset = 0): array
    {
        return $this->reviews->published($subject, $order, $limit, $offset);
    }

    public function count(SubjectRef $subject): int
    {
        return $this->reviews->countPublished($subject);
    }

    /**
     * The member's vote on the review: `true` helpful, `false` not helpful, `null` none.
     */
    public function voteOf(ReviewId $id, MemberId $voter): ?bool
    {
        return $this->reviews->voteOf($id, $voter);
    }

    /**
     * Whether the member may respond to the reviews of the subject.
     */
    public function mayRespond(SubjectRef $subject, ?MemberId $member): bool
    {
        return $member instanceof MemberId && $this->responders->mayRespond($subject, $member);
    }

    /**
     * Whether the member may review the subject now, and why not: `null` when they may.
     */
    public function refusal(SubjectRef $subject, MemberId $member): ?string
    {
        try {
            $type = $this->types->get($subject->type);

            if ($this->ratings->summary($subject)->suspended) {
                return 'suspended';
            }

            if ($type->participantsOnly) {
                $this->checkWindow($this->participations->of($subject, $member), $type->window, $this->clock->now());
            }
        } catch (InvalidReview) {
            return 'unknown';
        } catch (ReviewRefused) {
            return 'window';
        }

        return null;
    }

    /**
     * @throws ReviewRefused when the member took no part, their participation is to come, or its window closed
     */
    private function checkWindow(?Participation $participation, ?int $window, DateTimeImmutable $now): void
    {
        if (!$participation instanceof Participation) {
            throw ReviewRefused::because('Only those who took part review this subject.');
        }

        if ($participation->at > $now) {
            throw ReviewRefused::because('Your participation has not happened yet: review it afterwards.');
        }

        if (null !== $window && $now > $participation->at->modify(\sprintf('+%d days', $window))) {
            throw ReviewRefused::because(\sprintf('The reviews of this subject are taken within %d days of taking part.', $window));
        }
    }

    private function withdrawn(Review $review, ?string $criterion): Review
    {
        $review->withdraw($criterion, $this->clock->now());
        $this->reviews->save($review);
        $this->ratings->recompute($review->subject);
        $this->events?->dispatch(new ReviewWithdrawn($review->id->value, $review->subject->type, $review->subject->id));

        return $review;
    }

    /**
     * @throws ReviewRefused when the review does not exist
     */
    private function find(ReviewId $id): Review
    {
        return $this->reviews->get($id) ?? throw ReviewRefused::because('This review does not exist.');
    }

    /**
     * @throws ReviewRefused when the review does not exist or is not published
     */
    private function published(ReviewId $id): Review
    {
        $review = $this->find($id);

        if (!$review->isPublished()) {
            throw ReviewRefused::because('This review is not published.');
        }

        return $review;
    }

    /**
     * @throws ReviewRefused when the review is not the author's
     */
    private function mine(ReviewId $id, MemberId $author): Review
    {
        $review = $this->find($id);

        if (!$review->isBy($author)) {
            throw ReviewRefused::because('Only the author changes a review.');
        }

        return $review;
    }

    private function suspended(): ReviewRefused
    {
        return ReviewRefused::because('The reviews of this subject are suspended for now; the reviews given stay.');
    }

    private function already(?DuplicateReview $previous = null): ReviewRefused
    {
        return new ReviewRefused('You reviewed this subject already: change your review instead.', 0, $previous);
    }
}
