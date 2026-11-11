<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes;

/**
 * One problem of the notes of a document: its kind, the note it names, the block where it was found and, for a note
 * referenced several times, how many references it has.
 */
final readonly class Problem
{
    public function __construct(
        public ProblemKind $kind,
        public string $noteId,
        public string $blockId,
        public int $occurrences = 1,
    ) {
    }
}
