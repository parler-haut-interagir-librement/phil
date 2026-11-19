<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\View;

/**
 * What an entry of a view shows, in the order entries of one moment are listed.
 */
enum EntryKind: string
{
    case Batch = 'batch';
    case Item = 'item';
    case Transition = 'transition';

    public function rank(): int
    {
        return match ($this) {
            self::Batch => 0,
            self::Item => 1,
            self::Transition => 2,
        };
    }
}
