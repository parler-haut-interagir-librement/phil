<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Attribute\BitFlags;
use Phil\Enums\Traits\AsEnumerableEnumTrait;

/**
 * Bit flags, and an enum that marks no default case.
 */
#[BitFlags]
enum Permission: int
{
    use AsEnumerableEnumTrait;

    case Read = 1;
    case Write = 2;
    case Delete = 8;
}
