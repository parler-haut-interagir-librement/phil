<?php

declare(strict_types=1);

namespace Phil\TextDiff;

/**
 * One change between two texts: the old text it takes out and the new text it puts in — one of them empty for an
 * insertion or a deletion —, and where it happens in each text, in code points from the start.
 */
final readonly class Change
{
    public function __construct(
        public ChangeKind $kind,
        public string $old,
        public string $new,
        public int $oldOffset,
        public int $newOffset,
    ) {
    }
}
