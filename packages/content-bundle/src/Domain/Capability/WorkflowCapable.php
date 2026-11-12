<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

use DateTimeImmutable;

/**
 * A dimension row that moves through an editorial workflow: its current place, and when it was last published.
 *
 * The getter and setter of the place follow the method marking store of `symfony/workflow`, configured with
 * `property: workflowPlace` and a single state. The workflow itself — its places, its transitions, its
 * guards — is the application's, declared in its configuration and driven from its application layer; the
 * row only stores where it stands.
 *
 * Use `WorkflowTrait` for the implementation.
 */
interface WorkflowCapable
{
    public function getWorkflowPlace(): ?string;

    /**
     * @param array<array-key, mixed> $context the transition context the marking store passes; unused here
     */
    public function setWorkflowPlace(?string $workflowPlace, array $context = []): void;

    public function getWorkflowPublished(): ?DateTimeImmutable;

    public function setWorkflowPublished(?DateTimeImmutable $workflowPublished): void;
}
