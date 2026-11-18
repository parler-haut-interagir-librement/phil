<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * The learning record store did not keep a statement: it could not be reached in time, or it answered with an error.
 * The message names the status, never the statement nor what the store answered.
 */
final class RecordStoreUnavailable extends RuntimeException implements CoursesException
{
    public static function status(int $status): self
    {
        return new self(\sprintf('The learning record store did not keep the statement: it answered HTTP %d.', $status));
    }

    public static function unreachable(?Throwable $previous = null): self
    {
        return new self('The learning record store did not keep the statement: it could not be reached within the limits.', 0, $previous);
    }
}
