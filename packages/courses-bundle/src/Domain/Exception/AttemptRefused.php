<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Exception;

use RuntimeException;

/**
 * A commit of a SCORM session the platform does not take: its attempt token is unknown, forged or expired, it is not
 * the learner's signed in, or its session is no longer the attempt's running one.
 */
final class AttemptRefused extends RuntimeException implements CoursesException
{
    public function __construct(
        string $reason,
        public readonly bool $closed = false,
    ) {
        parent::__construct($reason);
    }

    public static function because(string $reason): self
    {
        return new self($reason, false);
    }

    /**
     * The token is good, but its session ended or another launch opened a newer one.
     */
    public static function closed(): self
    {
        return new self('This session of the attempt is over: launch the activity again.', true);
    }
}
