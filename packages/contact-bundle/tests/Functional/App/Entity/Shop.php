<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\Contact\ContactCard;

#[ORM\Entity]
class Shop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'phil_contact', nullable: true)]
        public ?ContactCard $contact = null,
    ) {
    }
}
