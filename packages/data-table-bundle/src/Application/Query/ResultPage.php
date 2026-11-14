<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Query;

/**
 * One page of rows, and how many rows match in all.
 */
final readonly class ResultPage
{
    /**
     * @param list<array<string, mixed>|object> $rows
     */
    public function __construct(
        public array $rows,
        public int $total,
    ) {
    }
}
