<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Exception;

use RuntimeException;

/**
 * A course, an activity, an enrollment, a request or a session that the stores do not hold.
 */
final class UnknownRecord extends RuntimeException implements CoursesException
{
    public static function of(string $kind, string $id): self
    {
        return new self(\sprintf('No %s "%s" is known.', $kind, $id));
    }
}
