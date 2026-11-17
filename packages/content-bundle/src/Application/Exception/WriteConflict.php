<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Exception;

use RuntimeException;
use Throwable;

/**
 * A flush the store refused because a unique key it writes was taken in the meantime by another transaction: two
 * publications of one slug at once, two publications of one locale at once. The operation that flushed knows what the
 * key was, and raises the error of the domain that says so; the transaction rolls back.
 */
final class WriteConflict extends RuntimeException
{
    public static function onFlush(Throwable $previous): self
    {
        return new self('A unique key written by this operation was taken by another one meanwhile.', 0, $previous);
    }
}
