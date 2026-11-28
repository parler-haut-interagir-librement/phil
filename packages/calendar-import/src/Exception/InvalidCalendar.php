<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Exception;

use UnexpectedValueException;

/**
 * A text refused whole: not iCalendar, not UTF-8, or built in a way no event can be read from — components that do not
 * nest, a line that is not a name, parameters and a value outside an event. An event that cannot be read is not this:
 * it is refused alone, with its reason, and the others are read.
 */
final class InvalidCalendar extends UnexpectedValueException implements UnreadableCalendar
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
