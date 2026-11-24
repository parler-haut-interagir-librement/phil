<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Moderation;

use Override;
use Phil\ModerationBundle\Application\Contract\ModerationTarget;
use Phil\ModerationBundle\Application\TargetView;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\ValueObject\MemberId as ModerationMember;
use Phil\ReviewsBundle\Application\Contract\SubjectDirectory;
use Phil\ReviewsBundle\Application\Reviews;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Model\Review;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;

/**
 * The reviews as a target of phil/moderation-bundle: a member reports a review in its one queue, and the decision of a
 * moderator comes back here. Hiding or removing a review holds it out of sight; `ModerationDecisionListener` then
 * records the published criterion the public reason of the decision names, or publishes the review again when it names
 * none. Approving or restoring publishes it.
 */
final readonly class ReviewTarget implements ModerationTarget
{
    public const string TYPE = 'review';

    public function __construct(
        private Reviews $reviews,
        private SubjectDirectory $subjects,
    ) {
    }

    #[Override]
    public function type(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function describe(string $id): ?TargetView
    {
        $review = $this->find($id);

        if (!$review instanceof Review) {
            return null;
        }

        $content = $review->content();
        $author = $review->author();

        return new TargetView(
            \sprintf('Review of %s', $this->subjects->title($review->subject)),
            $content->body ?? $content->title ?? \sprintf('Overall score: %d', $content->overall),
            $this->subjects->url($review->subject),
            $author instanceof \Phil\ReviewsBundle\Domain\ValueObject\MemberId ? ModerationMember::of($author->value) : null,
        );
    }

    #[Override]
    public function apply(string $id, DecisionAction $action): void
    {
        $review = $this->find($id);

        if (!$review instanceof Review) {
            return;
        }

        match ($action) {
            DecisionAction::Approve, DecisionAction::Restore => $this->reviews->publish($review->id),
            DecisionAction::Hide, DecisionAction::Remove => $review->isPublished() ? $this->reviews->hold($review->id) : $review,
            DecisionAction::Dismiss, DecisionAction::Warn => $review,
        };
    }

    private function find(string $id): ?Review
    {
        try {
            return $this->reviews->get(ReviewId::fromString($id));
        } catch (InvalidReview) {
            return null;
        }
    }
}
