<?php

declare(strict_types=1);

namespace Phil\Citations\Import;

/**
 * The bounds of an import, against hostile or broken files: the input refused past them, never truncated in silence.
 */
final readonly class ImportLimits
{
    /**
     * @param int $maxBytes       the longest input read
     * @param int $maxEntries     the most entries or records read
     * @param int $maxFieldLength the longest value of one field, `@string` macros expanded, in bytes
     * @param int $maxDepth       the deepest nesting of braces in a BibTeX value
     * @param int $maxNames       the most names kept in one field; those after are left out
     */
    public function __construct(
        public int $maxBytes = 5_000_000,
        public int $maxEntries = 10_000,
        public int $maxFieldLength = 65_536,
        public int $maxDepth = 32,
        public int $maxNames = 1_000,
    ) {
    }
}
