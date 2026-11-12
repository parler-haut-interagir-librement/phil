<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Ownable;
use Phil\DoctrineBehaviours\OwnerId;

/**
 * Ownable by hand, with no `ownerId` field: the eraser leaves it out.
 */
#[ORM\Entity]
#[ORM\Table(name: 'skate')]
class Skate implements Ownable
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
    }

    public function ownerId(): ?OwnerId
    {
        return null;
    }

    public function claimOwner(OwnerId $owner): void
    {
    }

    public function transferOwner(?OwnerId $owner): void
    {
    }
}
