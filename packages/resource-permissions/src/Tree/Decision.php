<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tree;

/**
 * What the entries of a tree say about an action.
 */
enum Decision: string
{
    case Allow = 'allow';
    case Deny = 'deny';

    /** No node from the resource to the root has an entry for the member: the application's default applies. */
    case Undecided = 'undecided';
}
