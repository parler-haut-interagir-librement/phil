<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture\Attribute;

use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/**
 * @extends AbstractMetaProperty<string>
 */
#[Attribute]
final class Color extends AbstractMetaProperty
{
    protected function transform(mixed $value): mixed
    {
        return 'tone-'.$value;
    }
}
