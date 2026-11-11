<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Traits\AsSelectableEnumTrait;

/**
 * A backed enum of one case: its options still map the name to the value.
 */
enum SingleCaseEnum: string
{
    use AsSelectableEnumTrait;

    case ONLY = 'only';
}
