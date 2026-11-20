<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\VersionableEntity;

/**
 * A mapped superclass that is versionable: it has no table, so no version belongs to it.
 */
#[ORM\MappedSuperclass]
abstract class Tracked implements Versionable
{
    use VersionableEntity;
}
