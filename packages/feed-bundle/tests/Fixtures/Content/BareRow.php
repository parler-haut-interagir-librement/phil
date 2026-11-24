<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Fixtures\Content;

use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowTrait;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;

/**
 * A row with the workflow alone: no excerpt, no SEO, no body.
 */
final class BareRow implements DimensionContent, WorkflowCapable
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
