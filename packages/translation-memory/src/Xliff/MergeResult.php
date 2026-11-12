<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Xliff;

use Phil\TranslationMemory\Segment;

/**
 * The segments of a document after an XLIFF file was merged into them: every segment, in its order, the keys of those
 * the file changed, and the units it skipped.
 */
final readonly class MergeResult
{
    /**
     * @param list<Segment> $segments
     * @param list<string>  $applied
     * @param list<Skipped> $skipped
     */
    public function __construct(
        public array $segments,
        public array $applied = [],
        public array $skipped = [],
    ) {
    }
}
