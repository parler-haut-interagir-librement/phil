<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\BlameableEntity;
use Phil\DoctrineBehaviours\Contract\Blameable;

/**
 * A mapped superclass with the blame: it has no table of its own, its entities have the columns in theirs.
 */
#[ORM\MappedSuperclass]
abstract class Contribution implements Blameable
{
    use BlameableEntity;
}
