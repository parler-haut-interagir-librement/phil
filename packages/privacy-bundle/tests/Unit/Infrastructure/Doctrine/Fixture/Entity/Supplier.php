<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A second entity on the mapped superclass: its personal field is reported once, on the class that declares it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'supplier')]
class Supplier extends Person
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 0;
}
