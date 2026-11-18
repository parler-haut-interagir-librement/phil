<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Exception;

use Phil\PrivacyBundle\Domain\Exception\PrivacyError;
use RuntimeException;
use Throwable;

/**
 * The lock of a work in steps expired, or its store could not confirm it, before a step: the work stopped there, and
 * the process that holds the lock now, or the next run, takes it over.
 */
final class LockLost extends RuntimeException implements PrivacyError
{
    public static function create(?Throwable $previous = null): self
    {
        return new self('The lock of this work was lost before its next step; the work stopped.', 0, $previous);
    }
}
