<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Model;

/**
 * How a relation is computed.
 */
enum RewriteKind: string
{
    /** The tuples written on the relation itself. */
    case Direct = 'direct';

    /** Another relation of the same object: every editor is a viewer. */
    case Computed = 'computed';

    /** A relation of the objects a relation points at: the viewers of the parent folder view the document. */
    case FromRelated = 'from_related';

    /** Any of the rewrites. */
    case Union = 'union';

    /** Every one of the rewrites. */
    case Intersection = 'intersection';

    /** The first rewrite, unless the second. */
    case Exclusion = 'exclusion';
}
