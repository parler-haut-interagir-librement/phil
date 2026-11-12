<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Infrastructure\Workflow;

use Symfony\Component\Workflow\WorkflowInterface;

/**
 * A workflow's subject, its workflow, and the resolver that found it and saves it.
 */
final readonly class Subject
{
    public function __construct(
        public object $object,
        public WorkflowInterface $workflow,
        public SubjectResolver $resolver,
    ) {
    }
}
