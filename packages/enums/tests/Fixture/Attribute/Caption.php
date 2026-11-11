<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture\Attribute;

use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/**
 * A meta property that claims the accessor `label()`, which `Label` already names.
 *
 * @extends AbstractMetaProperty<string>
 */
#[Attribute]
final class Caption extends AbstractMetaProperty
{
    protected static function customMethodName(): string
    {
        return 'label';
    }
}
