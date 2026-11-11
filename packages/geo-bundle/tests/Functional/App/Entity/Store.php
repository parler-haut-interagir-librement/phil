<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\Geo\Coordinates;

#[ORM\Entity]
class Store
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 64)]
        public string $name,
        #[ORM\Embedded(class: Coordinates::class)]
        public Coordinates $position,
    ) {
    }
}
