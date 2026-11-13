<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Exception;

use RuntimeException;

/**
 * The parent links of a tree are not a tree: a node with two parents, or a node that is its own ancestor.
 */
final class InvalidTree extends RuntimeException implements ResourcePermissionsException
{
    public static function twoParents(string $node): self
    {
        return new self(\sprintf('The node "%s" has more than one parent.', $node));
    }

    public static function cycle(string $node): self
    {
        return new self(\sprintf('The node "%s" is its own ancestor.', $node));
    }
}
