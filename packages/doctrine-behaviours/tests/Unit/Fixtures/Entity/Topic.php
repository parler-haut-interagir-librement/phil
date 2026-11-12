<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\TreeNode;
use Phil\DoctrineBehaviours\TreeNodeEntity;

/**
 * A tree node identified by the caller.
 */
#[ORM\Entity]
#[ORM\Table(name: 'topic')]
class Topic implements TreeNode
{
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
