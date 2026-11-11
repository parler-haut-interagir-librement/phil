<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

/**
 * Bit flags that leave out the lowest bit.
 */
enum HighFlag: int
{
    case Two = 2;
    case Four = 4;
}
