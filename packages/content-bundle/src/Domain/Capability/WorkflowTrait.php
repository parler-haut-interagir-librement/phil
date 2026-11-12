<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

use DateTimeImmutable;

/**
 * The implementation of `WorkflowCapable`, without any persistence mapping.
 *
 * @phpstan-require-implements WorkflowCapable
 */
trait WorkflowTrait
{
    private ?string $workflowPlace = null;

    private ?DateTimeImmutable $workflowPublished = null;

    public function getWorkflowPlace(): ?string
    {
        return $this->workflowPlace;
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function setWorkflowPlace(?string $workflowPlace, array $context = []): void
    {
        $this->workflowPlace = $workflowPlace;
    }

    public function getWorkflowPublished(): ?DateTimeImmutable
    {
        return $this->workflowPublished;
    }

    public function setWorkflowPublished(?DateTimeImmutable $workflowPublished): void
    {
        $this->workflowPublished = $workflowPublished;
    }
}
