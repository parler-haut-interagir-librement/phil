<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\OpeningHours\OpeningHours;

#[ORM\Entity]
class Shop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'phil_opening_hours', nullable: true)]
        public ?OpeningHours $hours = null,
    ) {
    }
}
