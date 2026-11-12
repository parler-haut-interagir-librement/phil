<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

/**
 * The three twilights, each from the moment the Sun's centre is so many degrees below the horizon to sunrise, and from
 * sunset back to that moment.
 */
enum Twilight: string
{
    case Civil = 'civil';
    case Nautical = 'nautical';
    case Astronomical = 'astronomical';

    /**
     * How far below the horizon the Sun's centre is when it begins and ends, in degrees.
     */
    public function depression(): float
    {
        return match ($this) {
            self::Civil => 6.0,
            self::Nautical => 12.0,
            self::Astronomical => 18.0,
        };
    }
}
