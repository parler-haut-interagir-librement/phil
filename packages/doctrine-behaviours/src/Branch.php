<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Phil\DoctrineBehaviours\Contract\TreeNode;

/**
 * A node of a hierarchy and the branches under it.
 *
 * @template T of TreeNode
 */
final readonly class Branch
{
    /**
     * @param T               $node
     * @param list<Branch<T>> $children
     */
    public function __construct(
        public TreeNode $node,
        public array $children = [],
    ) {
    }
}
