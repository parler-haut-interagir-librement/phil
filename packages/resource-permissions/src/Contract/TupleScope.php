<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Contract;

use Phil\ResourcePermissions\Exception\UnscopedTuples;

/**
 * The partition of the tuples the current unit of work reads and writes: a tenant of a multi-tenant application, or
 * `Partition::DEFAULT` when the tuples are not partitioned. A store asks for it on every call and reads and writes
 * that partition alone, so that a tuple of one tenant never grants anything on another tenant's resource of the same
 * name.
 *
 * A scope that requires a partition and has none throws `UnscopedTuples`: the call fails rather than read or write
 * the tuples of every tenant, or of none in particular.
 */
interface TupleScope
{
    /**
     * `Partition::DEFAULT`, or 1 to `Partition::MAX_LENGTH` bytes of UTF-8 without control characters.
     *
     * @throws UnscopedTuples when the unit of work acts in no partition while one is required
     */
    public function partition(): string;
}
