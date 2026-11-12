<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Application;

use Phil\Resource\ResourceReference;

/**
 * Applies transitions to one kind of resource. The application writes one, or uses the bundle's handler over
 * `symfony/workflow`; the bundle finds them by autoconfiguration.
 */
interface TransitionHandler
{
    public function supports(ResourceReference $resource, string $transition): bool;

    /**
     * Applies the transition; an exception is a failed attempt, retried at the next run.
     */
    public function apply(ResourceReference $resource, string $transition): void;
}
