<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * A review the store refused because its author already reviewed the subject: the unique constraint of the store
 * decides, so that two requests at the same moment cannot both write one.
 */
final class DuplicateReview extends RuntimeException implements ReviewsException
{
    public static function of(string $subject, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The member already reviewed %s.', $subject), 0, $previous);
    }
}
