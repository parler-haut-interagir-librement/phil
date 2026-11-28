<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use Phil\LocalPickup\Tests\Support\RandomIntSpy;
use Random\Randomizer;

/**
 * Stands in for the global `random_int()` inside the package's namespace, to see the bounds the code draws between.
 */
function random_int(int $min, int $max): int
{
    RandomIntSpy::$bounds[] = [$min, $max];

    return new Randomizer()->getInt($min, $max);
}
