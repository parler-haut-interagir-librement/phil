<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Exception;

use InvalidArgumentException;

/**
 * A value that cannot be kept: an identifier, a date, a weight, a score or a setting out of bounds.
 */
final class InvalidInput extends InvalidArgumentException implements ProposalReviewException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
