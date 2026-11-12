<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Versioned under the table of its root.
 */
#[ORM\Entity]
class Report extends Document
{
}
