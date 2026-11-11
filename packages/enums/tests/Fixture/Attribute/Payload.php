<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture\Attribute;

use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/**
 * A meta property of any value, to see how an error message writes a value that is not a string.
 *
 * @extends AbstractMetaProperty<mixed>
 */
#[Attribute]
final class Payload extends AbstractMetaProperty
{
}
