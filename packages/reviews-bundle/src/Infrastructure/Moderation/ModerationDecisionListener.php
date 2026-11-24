<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Moderation;

use Phil\ModerationBundle\Application\Event\DecisionTaken;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ReviewsBundle\Application\RemovalCriteria;
use Phil\ReviewsBundle\Application\Reviews;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Model\Review;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Psr\Log\LoggerInterface;

/**
 * Keeps the moderation of the reviews within the published criteria. A decision that hides or removes a review must
 * name one of them in its public reason — the code alone, or the code followed by ":" and an explanation —: the review
 * is then withdrawn under it. A decision that names none publishes the review again, and the refusal is logged at
 * warning level, with the review and the decision.
 */
final readonly class ModerationDecisionListener
{
    public function __construct(
        private Reviews $reviews,
        private RemovalCriteria $criteria,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(DecisionTaken $event): void
    {
        $decision = $event->decision;

        if (ReviewTarget::TYPE !== $decision->target->type || !\in_array($decision->action, [DecisionAction::Hide, DecisionAction::Remove], true)) {
            return;
        }

        try {
            $review = $this->reviews->get(ReviewId::fromString($decision->target->id));
        } catch (InvalidReview) {
            return;
        }

        if (!$review instanceof Review || $review->isPublished()) {
            return;
        }

        $criterion = $this->criteria->fromReason($decision->publicReason);

        if (null !== $criterion) {
            $this->reviews->withdraw($review->id, $criterion);

            return;
        }

        $this->reviews->publish($review->id);
        $this->logger?->warning('A decision of the moderation withdrew a review without naming a published criterion: the review is published again.', [
            'review' => $review->id->value,
            'decision' => $decision->id,
        ]);
    }
}
