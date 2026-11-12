<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;

/**
 * A test double of an application's root. It belongs to no application: putting it in `src/` would make it
 * a root someone ends up using.
 */
final class ExampleRoot implements ContentRoot
{
    public function createDimensionContent(DimensionKey $dimension): ExampleDimensionContent
    {
        return new ExampleDimensionContent($this, $dimension);
    }
}
