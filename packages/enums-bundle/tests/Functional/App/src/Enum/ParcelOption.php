<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Functional\App\src\Enum;

use Phil\Enums\Attribute\BitFlags;
use Phil\Enums\Attribute\Group;
use Phil\Enums\Attribute\Label;

/**
 * The options of a parcel, bit flags stored as a bitmask.
 */
#[BitFlags]
enum ParcelOption: int
{
    #[Label('Fragile')] #[Group('care')]
    case Fragile = 1;

    #[Label('Insured')] #[Group('care')]
    case Insured = 2;

    #[Label('Express')]
    case Express = 4;
}
