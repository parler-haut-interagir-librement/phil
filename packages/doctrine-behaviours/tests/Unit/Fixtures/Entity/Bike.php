<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Ownable;
use Phil\DoctrineBehaviours\OwnableEntity;

/**
 * Belongs to someone.
 */
#[ORM\Entity]
#[ORM\Table(name: 'bike')]
class Bike implements Ownable
{
    use OwnableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column]
        public string $colour = 'red',
    ) {
    }
}
