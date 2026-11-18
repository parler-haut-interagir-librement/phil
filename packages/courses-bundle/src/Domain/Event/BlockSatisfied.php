<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A learner satisfied a block of a cmi5 course: every required unit it holds, nested blocks included, and the
 * platform issued its `satisfied` statement, once for the block and the enrollment. Identifiers only: a listener reads
 * the rest from the stores.
 */
final readonly class BlockSatisfied
{
    public function __construct(
        public string $enrollment,
        public string $block,
        public string $statement,
    ) {
    }
}
