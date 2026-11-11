<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Traits\AsStringSelectableEnumTrait;

/**
 * A pure enum written out as options: each case's name is its value.
 */
enum SelectablePureEnum
{
    use AsStringSelectableEnumTrait;

    case IN_TRANSIT;
    case OnHold;
    case ACTIVE;
}
