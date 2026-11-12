<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Timestampable;

/**
 * `Timestampable`, mapped: the columns `created_at` and `updated_at`, in UTC.
 *
 * @phpstan-require-implements Timestampable
 */
trait TimestampableEntity
{
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    protected ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    protected ?DateTimeImmutable $updatedAt = null;

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function stampCreated(DateTimeImmutable $at): void
    {
        $this->createdAt ??= $at;
        $this->updatedAt ??= $this->createdAt;
    }

    public function stampUpdated(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }
}
