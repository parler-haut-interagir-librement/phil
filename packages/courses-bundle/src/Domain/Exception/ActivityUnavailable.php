<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Exception;

use RuntimeException;

/**
 * An activity the learner cannot start or finish now: not released yet, waiting for an earlier activity, out of tries,
 * or one that only its own content can report.
 */
final class ActivityUnavailable extends RuntimeException implements CoursesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
