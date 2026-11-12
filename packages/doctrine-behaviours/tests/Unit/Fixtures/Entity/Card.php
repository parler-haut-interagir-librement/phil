<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\SoftDeletable;
use Phil\DoctrineBehaviours\SoftDeletableEntity;

#[ORM\Entity]
#[ORM\Table(name: 'card')]
class Card implements SoftDeletable
{
    use SoftDeletableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\ManyToOne(targetEntity: Shelf::class, inversedBy: 'cards')]
        #[ORM\JoinColumn(nullable: false)]
        public Shelf $shelf,
    ) {
    }
}
