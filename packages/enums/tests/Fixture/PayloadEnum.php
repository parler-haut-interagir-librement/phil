<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Tests\Fixture\Attribute\Payload;
use Phil\Enums\Traits\AsCollectableEnumTrait;

/**
 * A meta value that cannot be an array key.
 */
enum PayloadEnum
{
    use AsCollectableEnumTrait;

    #[Payload(['heavy'])]
    case ONE;
}
