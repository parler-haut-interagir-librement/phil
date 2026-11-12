<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'label')]
class Label
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\ManyToOne(targetEntity: Shelf::class, inversedBy: 'labels')]
        #[ORM\JoinColumn(nullable: false)]
        public Shelf $shelf,
    ) {
    }
}
