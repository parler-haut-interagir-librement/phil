<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

/**
 * Where the Sun stays in a solar day, against an altitude.
 */
enum Altitude
{
    /** It rises through it and sets through it. */
    case Crosses;

    /** It stays above it all day: the midnight sun, or a white night for a twilight. */
    case AlwaysAbove;

    /** It stays below it all day: the polar night, or a dark night for a twilight. */
    case AlwaysBelow;
}
