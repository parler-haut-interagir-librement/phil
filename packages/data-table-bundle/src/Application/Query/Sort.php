<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Query;

/**
 * A sort on one field.
 */
final readonly class Sort
{
    public function __construct(
        public string $field,
        public SortDirection $direction = SortDirection::Asc,
    ) {
    }
}
