<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\BlameableEntity;
use Phil\DoctrineBehaviours\Contract\Blameable;
use Phil\DoctrineBehaviours\Contract\SoftDeletable;
use Phil\DoctrineBehaviours\Contract\Timestampable;
use Phil\DoctrineBehaviours\Contract\TreeNode;
use Phil\DoctrineBehaviours\SoftDeletableEntity;
use Phil\DoctrineBehaviours\TimestampableEntity;
use Phil\DoctrineBehaviours\TreeNodeEntity;

#[ORM\Entity]
#[ORM\Table(name: 'page')]
class Page implements Timestampable, Blameable, SoftDeletable, TreeNode
{
    use BlameableEntity;
    use SoftDeletableEntity;
    use TimestampableEntity;
    use TreeNodeEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\ManyToOne(targetEntity: self::class)]
        public ?self $parent = null,
    ) {
    }

    public function treeId(): string
    {
        return $this->id;
    }

    public function treeParent(): ?self
    {
        return $this->parent;
    }
}
