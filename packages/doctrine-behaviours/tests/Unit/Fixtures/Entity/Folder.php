<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\TreeNode;
use Phil\DoctrineBehaviours\TreeNodeEntity;

/**
 * A tree node identified by the database: its path is known once it is inserted.
 */
#[ORM\Entity]
#[ORM\Table(name: 'folder')]
#[ORM\Index(columns: ['tree_path'])]
class Folder implements TreeNode
{
    use TreeNodeEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $name,
        #[ORM\ManyToOne(targetEntity: self::class)]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        public ?self $parent = null,
    ) {
    }

    public function treeId(): string
    {
        return (string) $this->id;
    }

    public function treeParent(): ?self
    {
        return $this->parent;
    }
}
