<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A subclass in a table of its own, whose position its parent's table holds.
 */
#[ORM\Entity]
#[ORM\Table(name: 'encore')]
class Encore extends Stage
{
    #[ORM\Column]
    public string $song = '';
}
