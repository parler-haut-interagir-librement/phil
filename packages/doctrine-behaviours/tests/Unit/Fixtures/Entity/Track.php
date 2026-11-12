<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Positionable;
use Phil\DoctrineBehaviours\PositionableEntity;

/**
 * One order for the whole table.
 */
#[ORM\Entity]
#[ORM\Table(name: 'track')]
class Track implements Positionable
{
    use PositionableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
    }
}
