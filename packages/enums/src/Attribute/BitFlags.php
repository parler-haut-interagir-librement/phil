<?php

declare(strict_types=1);

namespace Phil\Enums\Attribute;

use Attribute;

/**
 * Declares an enum of bit flags, whose sets are stored as bitmasks: the PHPStan extension then reports a case
 * whose value is not a power of two, before `EnumSet::toBitmask()` refuses it at run time.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class BitFlags
{
}
