<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application\Contract;

/**
 * The partitions that hold tuples, by name: the tenants `phil:access:purge --all-tenants` enters in turn. The default
 * partition, which belongs to no tenant, is never among them.
 */
interface TuplePartitions
{
    /**
     * @return list<string> each partition once, in a stable order
     */
    public function partitions(): array;
}
