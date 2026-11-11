<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture\Attribute;

use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/**
 * A meta property whose accessor is named by `customMethodName()` rather than after the class.
 *
 * @extends AbstractMetaProperty<string>
 */
#[Attribute]
final class Shade extends AbstractMetaProperty
{
    protected static function customMethodName(): string
    {
        return 'tint';
    }
}
