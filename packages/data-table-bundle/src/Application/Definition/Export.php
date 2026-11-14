<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Definition;

/**
 * The export a table offers: the voter attribute it asks beyond the table's own, and the most rows it writes.
 */
final readonly class Export
{
    /** The most rows by default. */
    public const int MAX_ROWS = 10_000;

    /** The most rows a table may allow. */
    public const int LIMIT = 1_000_000;

    public function __construct(
        public ?string $attribute = null,
        public int $maxRows = self::MAX_ROWS,
    ) {
    }
}
