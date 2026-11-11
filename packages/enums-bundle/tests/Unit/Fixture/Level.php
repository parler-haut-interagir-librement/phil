<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Fixture;

use Phil\Enums\Attribute\TransitionsTo;

/**
 * Levels backed by integers that move down: a workflow's places are their values as strings, and its initial
 * marking is not the first case.
 */
enum Level: int
{
    case One = 1;

    #[TransitionsTo(self::One)]
    case Two = 2;
}
