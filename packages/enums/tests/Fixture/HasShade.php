<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Attribute\Meta;
use Phil\Enums\Tests\Fixture\Attribute\Shade;

/**
 * A trait that declares a meta property of its own, for the enums that use it.
 */
#[Meta(Shade::class)]
trait HasShade
{
}
