<?php

declare(strict_types=1);

namespace Phil\UsageMeter;

/**
 * What a store did with an increment.
 */
enum Increment: string
{
    /** Counted. */
    case Recorded = 'recorded';
    /** Not counted: it would have taken the counter past its ceiling. */
    case Refused = 'refused';
    /** Not counted again: an increment of the same key was already counted. */
    case Duplicate = 'duplicate';
}
