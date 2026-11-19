<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\ValueObject;

/**
 * One cell of the privacy grid, by its row from the equator and its column from the prime meridian.
 */
final readonly class GridCell
{
    public function __construct(
        public int $row,
        public int $column,
    ) {
    }

    public function key(): string
    {
        return $this->row.':'.$this->column;
    }
}
