<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Ambiguous;

use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\RequiredMeta;

/**
 * A second enum named `Shape`, which makes the short name ambiguous, and whose `Square` misses the label the enum
 * requires.
 */
// @phpstan-ignore philEnums.requiredMeta
#[RequiredMeta(Label::class)]
enum Shape
{
    #[Label('Circle')]
    case Circle;

    case Square;
}
