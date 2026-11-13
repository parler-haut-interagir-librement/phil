<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions;

use Phil\Resource\ResourceReference;

/**
 * The objects a subject holds a relation on, ordered by identifier. `$complete` is false when the list was cut: more
 * objects than the limit asked for, or more candidates than the budget lets the listing look at.
 */
final readonly class ObjectList
{
    /**
     * @param list<ResourceReference> $objects
     */
    public function __construct(
        public array $objects,
        public bool $complete,
    ) {
    }

    /**
     * The identifiers alone, for a query's `IN (…)`.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return array_map(static fn (ResourceReference $object): string => $object->id, $this->objects);
    }
}
