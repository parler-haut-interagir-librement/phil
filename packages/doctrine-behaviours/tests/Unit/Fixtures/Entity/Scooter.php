<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Inherits its owner from its parent: the eraser counts the table of the parent once.
 */
#[ORM\Entity]
class Scooter extends Vehicle
{
}
