<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value the reviews refuse: an identifier, a score out of the scale, a text too long, an unknown criterion.
 */
final class InvalidReview extends InvalidArgumentException implements ReviewsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
