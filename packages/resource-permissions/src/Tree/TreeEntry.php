<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tree;

/**
 * One entry of a node for an action: a grantee, and whether the entry allows or denies.
 */
final readonly class TreeEntry
{
    public function __construct(
        public Grantee $grantee,
        public Decision $decision,
    ) {
    }
}
