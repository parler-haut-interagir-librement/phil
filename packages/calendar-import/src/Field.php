<?php

declare(strict_types=1);

namespace Phil\CalendarImport;

/**
 * A field of an imported event an editor may replace, and whose change by the source is reported when it is replaced.
 */
enum Field: string
{
    case Title = 'title';
    case Location = 'location';
    case Description = 'description';
    case Tags = 'tags';
}
