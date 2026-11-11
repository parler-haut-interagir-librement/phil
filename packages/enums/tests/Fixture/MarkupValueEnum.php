<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Traits\AsCollectableEnumTrait;
use Phil\Enums\Traits\AsStringSelectableEnumTrait;

/**
 * Values that hold what HTML gives a meaning to, and an integer-backed sibling below: what the default options must
 * escape.
 */
enum MarkupValueEnum: string
{
    use AsCollectableEnumTrait;
    use AsStringSelectableEnumTrait;

    case Quoted = 'x"><script>';
    case Ampersand = "Tom & Jerry's";
}
