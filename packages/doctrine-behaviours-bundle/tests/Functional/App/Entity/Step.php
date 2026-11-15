<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\DoctrineBehaviours\Contract\Positionable;
use Phil\DoctrineBehaviours\PositionableEntity;

/**
 * A step of a checklist, ordered within its checklist only.
 */
#[ORM\Entity]
#[ORM\Table(name: 'step')]
class Step implements Positionable
{
    use PositionableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column(length: 36)]
        public string $checklist,
    ) {
    }

    #[Override]
    public function positionGroup(): array
    {
        return ['checklist'];
    }
}
