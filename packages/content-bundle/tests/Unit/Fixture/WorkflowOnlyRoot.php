<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;

/**
 * A root whose rows are published without a body: they carry the workflow and nothing else.
 */
final class WorkflowOnlyRoot implements ContentRoot
{
    public function createDimensionContent(DimensionKey $dimension): WorkflowOnlyDimensionContent
    {
        return new WorkflowOnlyDimensionContent($this, $dimension);
    }
}
