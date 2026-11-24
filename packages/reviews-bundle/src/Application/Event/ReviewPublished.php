<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application\Event;

/**
 * A withdrawn review was published again: restored by the moderation, or its contest upheld. It carries identifiers only.
 */
final readonly class ReviewPublished
{
    public function __construct(
        public string $review,
        public string $subjectType,
        public string $subjectId,
    ) {
    }
}
