<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Positionable;
use Phil\DoctrineBehaviours\PositionableEntity;

/**
 * A root of joined tables: the position of its subclasses lives in its own table.
 */
#[ORM\Entity]
#[ORM\Table(name: 'stage')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['encore' => Encore::class])]
abstract class Stage implements Positionable
{
    use PositionableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
    }
}
