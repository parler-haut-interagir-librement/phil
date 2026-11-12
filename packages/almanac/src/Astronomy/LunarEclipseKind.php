<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

/**
 * How deep the Moon goes into the Earth's shadow: wholly into the umbra, partly, or into the penumbra only.
 */
enum LunarEclipseKind: string
{
    case Total = 'total';
    case Partial = 'partial';
    case Penumbral = 'penumbral';
}
