<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Fixture;

use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/**
 * @extends AbstractMetaProperty<int>
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class Priority extends AbstractMetaProperty
{
}
