<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

use DateTimeImmutable;

/**
 * An entity that knows when it was created and last updated: `TimestampListener` stamps it as it is flushed.
 */
interface Timestampable
{
    public function createdAt(): ?DateTimeImmutable;

    public function updatedAt(): ?DateTimeImmutable;

    /**
     * Stamps a new entity, keeping a date it was already given.
     */
    public function stampCreated(DateTimeImmutable $at): void;

    public function stampUpdated(DateTimeImmutable $at): void;
}
