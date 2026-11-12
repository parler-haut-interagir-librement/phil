<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Deactivatable;

/**
 * `Deactivatable`, mapped: the columns `active` and `deactivated_at`, in UTC, null while the record is active.
 *
 * @phpstan-require-implements Deactivatable
 */
trait DeactivatableEntity
{
    #[ORM\Column(name: 'active', type: Types::BOOLEAN, options: ['default' => true])]
    protected bool $active = true;

    #[ORM\Column(name: 'deactivated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $deactivatedAt = null;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function deactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function reactivate(): void
    {
        $this->active = true;
    }

    public function stampDeactivated(?DateTimeImmutable $at): void
    {
        $this->deactivatedAt = $at;
    }
}
