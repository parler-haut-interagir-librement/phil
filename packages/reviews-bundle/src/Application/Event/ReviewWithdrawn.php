<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application\Event;

/**
 * A review was withdrawn, under a published criterion or held while the moderation looks at it. It carries identifiers only.
 */
final readonly class ReviewWithdrawn
{
    public function __construct(
        public string $review,
        public string $subjectType,
        public string $subjectId,
    ) {
    }
}
