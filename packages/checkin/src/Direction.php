<?php

declare(strict_types=1);

namespace Phil\Checkin;

/**
 * Whether a scan lets its holder in or sees them out.
 */
enum Direction: string
{
    case In = 'in';
    case Out = 'out';
}
