<?php

declare(strict_types=1);

namespace Phil\Slides\Domain;

use Phil\PagedMedia\Pdf\PageSize;

/**
 * The page a slide is printed on with its notes, upright: US Letter or A4.
 */
enum NotesPageSize: string
{
    case Letter = 'letter';
    case A4 = 'a4';

    public function pageSize(): PageSize
    {
        return match ($this) {
            self::Letter => PageSize::letter(),
            self::A4 => PageSize::a4(),
        };
    }
}
