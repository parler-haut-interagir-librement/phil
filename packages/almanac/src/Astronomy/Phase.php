<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

/**
 * The four principal phases of the Moon, each a quarter of a lunation after the one before.
 */
enum Phase: string
{
    case New = 'new moon';
    case FirstQuarter = 'first quarter';
    case Full = 'full moon';
    case LastQuarter = 'last quarter';

    /**
     * Its fraction of a lunation after the new moon.
     */
    public function fraction(): float
    {
        return match ($this) {
            self::New => 0.0,
            self::FirstQuarter => 0.25,
            self::Full => 0.5,
            self::LastQuarter => 0.75,
        };
    }
}
