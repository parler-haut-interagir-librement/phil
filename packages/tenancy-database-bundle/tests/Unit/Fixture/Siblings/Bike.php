<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Siblings;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bike')]
final class Bike extends Vehicle
{
}
