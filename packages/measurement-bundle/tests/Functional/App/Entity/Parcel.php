<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\Measurement\Measurement;

/**
 * A parcel whose weight is a measurement in one column, and whose length may be left out.
 */
#[ORM\Entity]
class Parcel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'phil_measurement')]
        public Measurement $weight,
        #[ORM\Column(type: 'phil_measurement', length: 40, nullable: true)]
        public ?Measurement $length = null,
    ) {
    }
}
