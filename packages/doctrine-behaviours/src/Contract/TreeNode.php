<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

/**
 * A node of a tree kept as a materialized path: `/1/4/9/` for a node 9 under 4 under the root 1. `TreeListener` writes
 * the path and the depth as the node is flushed and moves its branch with it; `Tree` reads children, descendants,
 * ancestors and the hierarchy from them.
 *
 * The identifier is known once the node is inserted, and holds no slash.
 */
interface TreeNode
{
    public const string PATH_FIELD = 'treePath';

    public const string DEPTH_FIELD = 'treeDepth';

    public function treeId(): string;

    public function treeParent(): ?self;

    public function treePath(): ?string;

    public function treeDepth(): ?int;

    /**
     * Places the node at a path, its depth following from it.
     */
    public function placeInTree(string $path): void;
}
