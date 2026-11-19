<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Exception;

use OutOfBoundsException;

/**
 * An item or a batch the calendar does not hold.
 */
final class UnknownItem extends OutOfBoundsException implements EditorialCalendarException
{
    public static function item(string $id): self
    {
        return new self(\sprintf('The calendar holds no item "%s".', $id));
    }

    public static function batch(string $id): self
    {
        return new self(\sprintf('The calendar holds no batch "%s".', $id));
    }
}
