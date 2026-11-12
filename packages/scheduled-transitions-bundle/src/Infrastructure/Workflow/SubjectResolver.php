<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Infrastructure\Workflow;

use Phil\Resource\ResourceReference;

/**
 * The objects a workflow moves: found by their reference, and saved once moved. The application writes one per kind
 * of resource; the bundle finds them by autoconfiguration.
 */
interface SubjectResolver
{
    public function resolve(ResourceReference $resource): ?object;

    public function saved(object $subject): void;
}
