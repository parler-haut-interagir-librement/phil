<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Exception;

use InvalidArgumentException;

/**
 * A value the package refuses: a period that ends before it starts or reaches too far, an event without a title, a
 * connection without a calendar.
 */
final class InvalidSync extends InvalidArgumentException implements CalendarSyncException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
