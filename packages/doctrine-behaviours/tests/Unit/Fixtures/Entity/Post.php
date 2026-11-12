<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\BlameableEntity;
use Phil\DoctrineBehaviours\Contract\Blameable;

/**
 * The root of a joined inheritance: the blame columns live in its table, not in its subclasses'.
 */
#[ORM\Entity]
#[ORM\Table(name: 'post')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['post' => Post::class, 'essay' => Essay::class])]
class Post implements Blameable
{
    use BlameableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
    }
}
