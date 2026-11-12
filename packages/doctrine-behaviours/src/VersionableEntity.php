<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Versionable;

/**
 * `Versionable`, mapped: the columns `version_number`, counted from one, and `version_frozen`. The entity says what a
 * version keeps, with `versionedState()` and `applyVersionedState()`, and which identifier it goes by.
 *
 * @phpstan-require-implements Versionable
 */
trait VersionableEntity
{
    #[ORM\Column(name: 'version_number', type: Types::INTEGER, options: ['default' => 1])]
    protected int $versionNumber = 1;

    #[ORM\Column(name: 'version_frozen', type: Types::BOOLEAN, options: ['default' => false])]
    protected bool $versionFrozen = false;

    public function versionNumber(): int
    {
        return $this->versionNumber;
    }

    public function isVersionFrozen(): bool
    {
        return $this->versionFrozen;
    }

    public function markVersionFrozen(): void
    {
        $this->versionFrozen = true;
    }

    public function forkVersion(): void
    {
        ++$this->versionNumber;
        $this->versionFrozen = false;
    }
}
