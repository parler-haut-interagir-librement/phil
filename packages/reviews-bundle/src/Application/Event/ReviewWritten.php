<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application\Event;

/**
 * A member wrote or changed a review. It carries identifiers only.
 */
final readonly class ReviewWritten
{
    public function __construct(
        public string $review,
        public string $subjectType,
        public string $subjectId,
    ) {
    }
}
