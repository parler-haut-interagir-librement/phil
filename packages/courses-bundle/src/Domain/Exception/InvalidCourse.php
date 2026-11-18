<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value a course, a module, an activity or a learner cannot hold: an identifier, a title, a release, a score or the
 * settings of an activity type.
 */
final class InvalidCourse extends InvalidArgumentException implements CoursesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
