<?php

declare(strict_types=1);

namespace Phil\CalendarImport\State;

/**
 * What told an event apart from its last version: its `SEQUENCE`, else its `LAST-MODIFIED`, else its content alone.
 */
enum ChangeReason: string
{
    case Sequence = 'sequence';
    case LastModified = 'last-modified';
    case Content = 'content';
}
