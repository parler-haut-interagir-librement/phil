<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Another table, the same slugs: its parent's.
 */
#[ORM\Entity]
#[ORM\Table(name: 'sibling_tag')]
class SiblingTag extends ParentTag
{
}
