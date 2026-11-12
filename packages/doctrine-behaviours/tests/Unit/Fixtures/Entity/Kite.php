<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Maps a field named like the owner without being `Ownable`: the eraser leaves it alone.
 */
#[ORM\Entity]
#[ORM\Table(name: 'kite')]
class Kite
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column(name: 'owner_id', length: 255)]
        public string $ownerId,
    ) {
    }
}
