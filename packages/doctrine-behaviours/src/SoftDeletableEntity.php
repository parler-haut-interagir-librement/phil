<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\SoftDeletable;

/**
 * `SoftDeletable`, mapped: the column `deleted_at`, in UTC, null while the entity is not deleted.
 *
 * @phpstan-require-implements SoftDeletable
 */
trait SoftDeletableEntity
{
    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $deletedAt = null;

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function markDeleted(DateTimeImmutable $at): void
    {
        $this->deletedAt = $at;
    }

    public function restore(): void
    {
        $this->deletedAt = null;
    }
}
