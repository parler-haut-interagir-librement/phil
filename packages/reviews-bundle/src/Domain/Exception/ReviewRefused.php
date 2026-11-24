<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\Exception;

use RuntimeException;

/**
 * An action the rules of the reviews refuse: a second review of one subject, a review outside the window of the
 * participants, a vote on one's own review, a removal outside the published criteria. The message can be shown.
 */
final class ReviewRefused extends RuntimeException implements ReviewsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
