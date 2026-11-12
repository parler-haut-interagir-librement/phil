<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

/**
 * What a solar eclipse is at its greatest: the Moon covering the whole Sun, a ring of it, both along its path, or
 * part of it only.
 */
enum SolarEclipseKind: string
{
    case Total = 'total';
    case Annular = 'annular';
    case Hybrid = 'hybrid';
    case Partial = 'partial';
}
