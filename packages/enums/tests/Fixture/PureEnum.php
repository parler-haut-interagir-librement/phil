<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Traits\AsComparableEnumTrait;
use Phil\Enums\Traits\AsFromambleEnumTrait;
use Phil\Enums\Traits\AsInvocableEnumTrait;
use Phil\Enums\Traits\AsNameableEnumTrait;
use Phil\Enums\Traits\AsSelectableEnumTrait;
use Phil\Enums\Traits\AsSelfAwareableEnumTrait;
use Phil\Enums\Traits\AsValuableEnumTrait;

enum PureEnum
{
    use AsComparableEnumTrait;
    use AsFromambleEnumTrait;
    use AsInvocableEnumTrait;
    use AsNameableEnumTrait;
    use AsSelectableEnumTrait;
    use AsSelfAwareableEnumTrait;
    use AsValuableEnumTrait;

    case ONE;
    case TWO;
    case THREE;
}
