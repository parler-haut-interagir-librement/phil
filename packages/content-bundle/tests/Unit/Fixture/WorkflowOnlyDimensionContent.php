<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowTrait;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;

/**
 * A row with the workflow only, no `BodyCapable`.
 */
final class WorkflowOnlyDimensionContent implements DimensionContent, WorkflowCapable
{
    use WorkflowTrait;

    public function __construct(
        private readonly ContentRoot $root,
        private readonly DimensionKey $dimension,
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
