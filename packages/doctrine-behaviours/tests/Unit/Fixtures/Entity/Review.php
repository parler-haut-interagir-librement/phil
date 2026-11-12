<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The blame of a mapped superclass, in a table whose name sorts before the class's.
 */
#[ORM\Entity]
#[ORM\Table(name: 'annotation')]
class Review extends Contribution
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
    }
}
