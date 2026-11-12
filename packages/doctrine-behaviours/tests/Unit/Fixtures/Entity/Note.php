<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with no behaviour, which every listener leaves alone.
 */
#[ORM\Entity]
#[ORM\Table(name: 'note')]
class Note
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column]
        public string $text,
    ) {
    }
}
