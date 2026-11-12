<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Positionable;
use Phil\DoctrineBehaviours\PositionableEntity;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\PrefixedType;

/**
 * Identified by its aisle and its number, ordered within its hall; the number and the hall are stored behind a prefix,
 * so that a move binds each value with its type.
 */
#[ORM\Entity]
#[ORM\Table(name: 'seat')]
class Seat implements Positionable
{
    use PositionableEntity {
        positionGroup as private noGroup;
    }

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $aisle,
        #[ORM\Id]
        #[ORM\Column(type: PrefixedType::NAME, length: 36)]
        public string $number,
        #[ORM\Column(type: PrefixedType::NAME, length: 36)]
        public string $hall = 'main',
    ) {
    }

    public function positionGroup(): array
    {
        return ['hall'];
    }
}
