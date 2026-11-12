<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Ownable;
use Phil\DoctrineBehaviours\OwnableEntity;

/**
 * Belongs to someone, in a table whose name sorts after the others while the class sorts first.
 */
#[ORM\Entity]
#[ORM\Table(name: 'zz_aircraft')]
class Aircraft implements Ownable
{
    use OwnableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
    }
}
