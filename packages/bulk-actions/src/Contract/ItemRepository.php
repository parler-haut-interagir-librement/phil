<?php

declare(strict_types=1);

namespace Phil\BulkActions\Contract;

use Phil\BulkActions\Selection\FilterCriteria;

/**
 * Reads the items of one target: by identifiers, and by the filters it declares. The library never builds a query; it
 * asks this port, and checks what it gets against the target's class.
 */
interface ItemRepository
{
    /**
     * Loads the items that still exist, by identifier; a missing one is left out.
     *
     * @param list<string> $identifiers at most one chunk of them
     *
     * @return array<array-key, object> the items by identifier; a numeric identifier is an integer key
     */
    public function find(array $identifiers): array;

    /**
     * Whether this repository knows the filter and accepts its parameters, asked at the selection and again when the
     * run executes.
     */
    public function supports(FilterCriteria $filter): bool;

    /**
     * How many items match the filter now, the excluded ones left out.
     *
     * @param list<string> $excluded
     */
    public function count(FilterCriteria $filter, array $excluded): int;

    /**
     * The identifiers matching the filter now, after the cursor, in a stable order such as the primary key's.
     *
     * @param list<string> $excluded
     * @param string|null  $after    the last identifier of the previous chunk, null for the first
     *
     * @return list<string> at most $limit identifiers
     */
    public function matching(FilterCriteria $filter, array $excluded, ?string $after, int $limit): array;

    /**
     * The identifier of an item, as find() takes it.
     */
    public function identify(object $item): string;

    /**
     * The item's version for optimistic locking, or null when the target keeps none.
     */
    public function version(object $item): ?string;
}
