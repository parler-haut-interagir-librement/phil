<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application\Event;

/**
 * The author of a withdrawn review contested the withdrawal. It carries identifiers only.
 */
final readonly class ContestFiled
{
    public function __construct(
        public string $review,
        public string $subjectType,
        public string $subjectId,
    ) {
    }
}
