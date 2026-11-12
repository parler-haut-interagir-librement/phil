<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\BlameableEntity;
use Phil\DoctrineBehaviours\Contract\Blameable;
use Phil\DoctrineBehaviours\Contract\SoftDeletable;
use Phil\DoctrineBehaviours\Contract\Timestampable;
use Phil\DoctrineBehaviours\SoftDeletableEntity;
use Phil\DoctrineBehaviours\TimestampableEntity;

#[ORM\Entity]
#[ORM\Table(name: 'article')]
class Article implements Timestampable, Blameable, SoftDeletable
{
    use BlameableEntity;
    use SoftDeletableEntity;
    use TimestampableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column]
        public string $title,
    ) {
    }
}
