<?php

declare(strict_types=1);

namespace Phil\CalendarImport\ICalendar;

/**
 * A bound of the reader, named by the error of a text that passes it.
 */
enum Limit: string
{
    case Bytes = 'bytes';
    case Events = 'events';
    case LineBytes = 'line-bytes';
    case Depth = 'depth';
    case Components = 'components';

    public function describe(): string
    {
        return match ($this) {
            self::Bytes => 'bytes in the text',
            self::Events => 'events',
            self::LineBytes => 'bytes in an unfolded line',
            self::Depth => 'components nested in one another',
            self::Components => 'components',
        };
    }
}
