<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The other subclass of the same table.
 */
#[ORM\Entity]
class Trike extends Vehicle
{
}
