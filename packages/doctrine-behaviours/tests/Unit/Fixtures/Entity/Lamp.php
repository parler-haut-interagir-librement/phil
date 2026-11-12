<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Deactivatable;
use Phil\DoctrineBehaviours\DeactivatableEntity;

/**
 * Switched off and back on.
 */
#[ORM\Entity]
#[ORM\Table(name: 'lamp')]
class Lamp implements Deactivatable
{
    use DeactivatableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column]
        public string $room = 'hall',
    ) {
    }
}
