<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture\Attribute;

use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/**
 * A meta property of integer value, to filter and sort by.
 *
 * @extends AbstractMetaProperty<int>
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class Mass extends AbstractMetaProperty
{
}
