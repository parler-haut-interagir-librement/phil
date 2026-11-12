<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Ownable;
use Phil\DoctrineBehaviours\OwnableEntity;

/**
 * A root of a single table, whose subclasses inherit the owner.
 */
#[ORM\Entity]
#[ORM\Table(name: 'vehicle')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['scooter' => Scooter::class, 'trike' => Trike::class])]
abstract class Vehicle implements Ownable
{
    use OwnableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
    }
}
