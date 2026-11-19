<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Exception;

use InvalidArgumentException;

/**
 * A planned item, an author, a batch or a view built from values it refuses.
 */
final class InvalidItem extends InvalidArgumentException implements EditorialCalendarException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
