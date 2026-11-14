<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\State;

/**
 * The columns a user keeps and the page size they read at; empty and zero when they chose nothing.
 */
final readonly class TablePreference
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        public array $columns = [],
        public int $pageSize = 0,
    ) {
    }
}
