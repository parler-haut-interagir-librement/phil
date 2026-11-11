<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture\Attribute;

use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/**
 * A meta property that counts how many times it is instantiated, to see the metadata reader keep what it read.
 *
 * @extends AbstractMetaProperty<string>
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
final class Counted extends AbstractMetaProperty
{
    public static int $instances = 0;

    protected function transform(mixed $value): mixed
    {
        ++self::$instances;

        return $value;
    }
}
