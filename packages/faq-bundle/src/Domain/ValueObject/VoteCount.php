<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Domain\ValueObject;

/**
 * The totals of the readers who found an answer useful, and of those who did not.
 */
final readonly class VoteCount
{
    public function __construct(
        public int $yes,
        public int $no,
    ) {
    }
}
