<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

/**
 * Integer values that are not all bit flags: 0 is no bit.
 */
enum ZeroFlag: int
{
    case One = 1;
    case None = 0;
}
