<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * An event that cannot be: no title, an end before its start, an unknown time zone, a host without name.
 */
final class InvalidEvent extends InvalidArgumentException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
