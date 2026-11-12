<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * In a table of its own, with its slug in its parent's.
 */
#[ORM\Entity]
#[ORM\Table(name: 'child_tag')]
class ChildTag extends ParentTag
{
}
