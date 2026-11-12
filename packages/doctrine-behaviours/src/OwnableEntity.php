<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Ownable;

/**
 * `Ownable`, mapped: the column `owner_id`, null while nobody owns the record.
 *
 * @phpstan-require-implements Ownable
 */
trait OwnableEntity
{
    #[ORM\Column(name: 'owner_id', type: Types::STRING, length: OwnerId::MAX_LENGTH, nullable: true)]
    protected ?string $ownerId = null;

    public function ownerId(): ?OwnerId
    {
        return null === $this->ownerId ? null : new OwnerId($this->ownerId);
    }

    public function claimOwner(OwnerId $owner): void
    {
        $this->ownerId ??= $owner->value;
    }

    public function transferOwner(?OwnerId $owner): void
    {
        $this->ownerId = $owner?->value;
    }
}
