<?php

declare(strict_types=1);

namespace Phil\BulkActions\Target;

use Phil\BulkActions\Internal\Check;
use Phil\BulkActions\Selection\Selection;

/**
 * The bounds of every bulk action: how many identifiers a page may send, how many items a run may touch, how many
 * items a chunk handles in one transaction, how many the preview samples, and how long a selection waits.
 */
final readonly class Limits
{
    public function __construct(
        public int $maxIdentifiers = 1_000,
        public int $maxItems = 10_000,
        public int $chunkSize = 100,
        public int $sampleSize = 10,
        public int $lifetimeSeconds = 900,
    ) {
        Check::between('maxIdentifiers', $maxIdentifiers, 1, Selection::MAX_IDENTIFIERS);
        Check::between('maxItems', $maxItems, 1, 1_000_000);
        Check::between('chunkSize', $chunkSize, 1, 1_000);
        Check::between('sampleSize', $sampleSize, 0, 100);
        Check::between('lifetimeSeconds', $lifetimeSeconds, 60, 86_400);
    }
}
