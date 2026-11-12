<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

use DateTimeImmutable;

/**
 * An entity that is marked deleted instead of leaving its table: `SoftDeleteListener` turns its removal into a date,
 * `SoftDeletedFilter` hides it from queries. Removed once more, it is deleted for good.
 */
interface SoftDeletable
{
    public function deletedAt(): ?DateTimeImmutable;

    public function isDeleted(): bool;

    public function markDeleted(DateTimeImmutable $at): void;

    public function restore(): void;
}
