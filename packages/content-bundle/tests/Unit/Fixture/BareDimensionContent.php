<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;

/**
 * A row without any capability, to check that a merger leaves alone what it does not know.
 */
final readonly class BareDimensionContent implements DimensionContent
{
    public function __construct(
        private ContentRoot $root,
        private DimensionKey $dimension,
    ) {
    }

    public function root(): ContentRoot
    {
        return $this->root;
    }

    public function dimension(): DimensionKey
    {
        return $this->dimension;
    }
}
