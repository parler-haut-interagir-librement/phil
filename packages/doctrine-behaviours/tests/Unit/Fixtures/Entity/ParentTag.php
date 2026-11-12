<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Sluggable;
use Phil\DoctrineBehaviours\SluggableEntity;

/**
 * A joined root: its children keep their slug in this table.
 */
#[ORM\Entity]
#[ORM\Table(name: 'parent_tag')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['child' => ChildTag::class, 'sibling' => SiblingTag::class])]
abstract class ParentTag implements Sluggable
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
