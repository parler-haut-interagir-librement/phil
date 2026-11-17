<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions;

use Override;
use Phil\ResourcePermissions\Contract\TupleScope;

/**
 * Every tuple in the default partition: the scope of an application with one tenant, and of the stores built without
 * a scope.
 */
final readonly class SinglePartition implements TupleScope
{
    #[Override]
    public function partition(): string
    {
        return Partition::DEFAULT;
    }
}
