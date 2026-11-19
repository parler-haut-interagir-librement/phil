<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Exception;

use DomainException;
use Phil\EditorialCalendar\ItemState;

/**
 * A change of state the workflow of an item does not allow, or a scheduled state without a moment.
 */
final class InvalidTransition extends DomainException implements EditorialCalendarException
{
    public static function between(string $item, ItemState $from, ItemState $to): self
    {
        return new self(\sprintf('The item "%s" cannot go from "%s" to "%s".', $item, $from->value, $to->value));
    }

    public static function inBatch(string $item, string $batch): self
    {
        return new self(\sprintf('The item "%s" is published with its batch "%s", as a whole.', $item, $batch));
    }

    public static function withoutMoment(string $item): self
    {
        return new self(\sprintf('The item "%s" is scheduled only with a moment to publish it at.', $item));
    }
}
