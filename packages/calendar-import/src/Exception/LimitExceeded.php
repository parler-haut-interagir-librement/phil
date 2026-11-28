<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Exception;

use Phil\CalendarImport\ICalendar\Limit;
use UnexpectedValueException;

/**
 * A text past one of the bounds of the reader, refused whole before it is read further: the bound it passed is named.
 */
final class LimitExceeded extends UnexpectedValueException implements UnreadableCalendar
{
    public function __construct(
        public readonly Limit $limit,
        public readonly int $max,
    ) {
        parent::__construct(\sprintf('The calendar is past its limit: %s, %d at most.', $limit->describe(), $max));
    }

    public static function of(Limit $limit, int $max): self
    {
        return new self($limit, $max);
    }
}
