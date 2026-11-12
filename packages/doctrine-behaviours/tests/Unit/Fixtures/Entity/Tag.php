<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Sluggable;
use Phil\DoctrineBehaviours\SluggableEntity;

/**
 * A slug made of its name.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tag')]
class Tag implements Sluggable
{
    use SluggableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column]
        public string $name,
    ) {
    }

    public function slugSource(): string
    {
        return $this->name;
    }
}
