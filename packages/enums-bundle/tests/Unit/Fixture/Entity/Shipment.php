<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\EnumsBundle\Tests\Unit\Fixture\Delivery;

/**
 * A shipment whose status moves as `Delivery` declares, or as its form's previous status says.
 */
#[ORM\Entity]
#[ORM\Table(name: 'shipment')]
class Shipment
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(enumType: Delivery::class)]
    public Delivery $status = Delivery::Ordered;

    #[ORM\Column(nullable: true, enumType: Delivery::class)]
    public ?Delivery $returned = null;

    public ?Delivery $previous = null;
}
