<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Merger;

use DateTimeImmutable;
use Override;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * Merges `WorkflowCapable`: the source's place and publication date, each only when the source sets it.
 */
final readonly class WorkflowMerger implements Merger
{
    #[Override]
    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if (!$target instanceof WorkflowCapable || !$source instanceof WorkflowCapable) {
            return;
        }

        $place = $source->getWorkflowPlace();

        if (null !== $place) {
            $target->setWorkflowPlace($place);
        }

        $published = $source->getWorkflowPublished();

        if ($published instanceof DateTimeImmutable) {
            $target->setWorkflowPublished($published);
        }
    }
}
