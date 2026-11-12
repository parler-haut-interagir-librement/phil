<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Mapping;

use Override;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;

/**
 * `WorkflowCapable`: the place in the workflow, and the date of the last publication.
 *
 * The date is a `datetime_immutable`, which stores no time zone: the application writes it in one zone, UTC
 * by preference, and reads it back in the same.
 */
final class WorkflowCapabilityMapping extends CapabilityMapping
{
    #[Override]
    public function capability(): string
    {
        return WorkflowCapable::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [
            ['fieldName' => 'workflowPlace', 'type' => 'string', 'columnName' => 'workflow_place', 'length' => 32, 'nullable' => true],
            ['fieldName' => 'workflowPublished', 'type' => 'datetime_immutable', 'columnName' => 'workflow_published', 'nullable' => true],
        ];
    }
}
