<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Blameable;

/**
 * `Blameable`, mapped: the columns `created_by` and `updated_by`, the identifiers of the users, null when nobody known
 * did it.
 *
 * @phpstan-require-implements Blameable
 */
trait BlameableEntity
{
    #[ORM\Column(name: 'created_by', type: Types::STRING, length: 255, nullable: true)]
    protected ?string $createdBy = null;

    #[ORM\Column(name: 'updated_by', type: Types::STRING, length: 255, nullable: true)]
    protected ?string $updatedBy = null;

    public function createdBy(): ?string
    {
        return $this->createdBy;
    }

    public function updatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function blameCreated(string $who): void
    {
        $this->createdBy ??= $who;
        $this->updatedBy ??= $this->createdBy;
    }

    public function blameUpdated(string $who): void
    {
        $this->updatedBy = $who;
    }
}
