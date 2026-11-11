<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\Address\Address;
use Phil\AddressBundle\Infrastructure\Validator\ValidAddress;

#[ORM\Entity]
class Shop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'phil_address', nullable: true)]
        #[ValidAddress]
        public ?Address $address = null,
    ) {
    }
}
