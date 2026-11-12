<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\TreeNode;

/**
 * The path and the depth of a `TreeNode`, mapped: the columns `tree_path`, indexed by the entity, and `tree_depth`, the
 * root being at depth 0. The entity maps its own parent and identifier.
 *
 * @phpstan-require-implements TreeNode
 */
trait TreeNodeEntity
{
    #[ORM\Column(name: 'tree_path', type: Types::STRING, length: 750, nullable: true)]
    protected ?string $treePath = null;

    #[ORM\Column(name: 'tree_depth', type: Types::INTEGER, nullable: true)]
    protected ?int $treeDepth = null;

    public function treePath(): ?string
    {
        return $this->treePath;
    }

    public function treeDepth(): ?int
    {
        return $this->treeDepth;
    }

    public function placeInTree(string $path): void
    {
        $this->treePath = $path;
        $this->treeDepth = substr_count($path, '/') - 2;
    }
}
