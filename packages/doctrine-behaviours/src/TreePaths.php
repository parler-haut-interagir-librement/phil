<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Phil\DoctrineBehaviours\Contract\TreeNode;

/**
 * The path a node takes under its parent.
 *
 * @internal
 */
final class TreePaths
{
    public static function of(TreeNode $node): string
    {
        $id = $node->treeId();

        if ('' === $id || str_contains($id, '/')) {
            throw InvalidTree::because(\sprintf('A tree node is identified without a slash, and by something; "%s" is not.', $id));
        }

        $parent = $node->treeParent();

        if (!$parent instanceof TreeNode) {
            return '/'.$id.'/';
        }

        $above = $parent->treePath() ?? throw InvalidTree::because(\sprintf('The parent of the tree node "%s" has no path: flush the parent first.', $id));
        $current = $node->treePath();

        if (null !== $current && str_starts_with($above, $current)) {
            throw InvalidTree::because(\sprintf('The tree node "%s" cannot move under itself or one of its descendants.', $id));
        }

        return $above.$id.'/';
    }

    /**
     * The LIKE pattern of a branch: the path, its wildcards escaped by "!", followed by anything.
     */
    public static function like(string $path): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $path).'%';
    }
}
