<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

/**
 * Where a weekday falls from a day.
 */
enum Relation: string
{
    case OnOrAfter = 'on or after';
    case After = 'after';
    case OnOrBefore = 'on or before';
    case Before = 'before';
}
