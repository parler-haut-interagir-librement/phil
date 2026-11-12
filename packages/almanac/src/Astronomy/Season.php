<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

/**
 * The four moments of the year when the Sun's apparent longitude is a multiple of 90°.
 */
enum Season: string
{
    case MarchEquinox = 'march equinox';
    case JuneSolstice = 'june solstice';
    case SeptemberEquinox = 'september equinox';
    case DecemberSolstice = 'december solstice';
}
