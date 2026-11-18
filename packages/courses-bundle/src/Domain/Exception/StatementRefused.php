<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Exception;

use RuntimeException;

/**
 * An xAPI statement the cmi5 rules refuse: another actor, registration, session or activity, a verb out of order, a
 * result that contradicts the verb, or a statement after the session ended.
 */
final class StatementRefused extends RuntimeException implements CoursesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
