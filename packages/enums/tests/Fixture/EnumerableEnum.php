<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Traits\AsEnumerableEnumTrait;

/**
 * Every trait at once, through the aggregate one.
 */
#[Meta(Label::class)]
enum EnumerableEnum: string
{
    use AsEnumerableEnumTrait;

    #[Label('Open')]
    case OPEN = 'open';

    #[Label('Closed')]
    case CLOSED = 'closed';
}
