<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes;

/**
 * A note that the text references: its identifier, its inline HTML as the document holds it, its number and written
 * label, the run of numbering it belongs to, and its references in reading order — at least one.
 */
final readonly class Note
{
    /**
     * @param string                        $html       the note's text, to be sanitized when written
     * @param int                           $number     the rank of the note in its run of numbering, from 1
     * @param string                        $label      the number as written: `3`, `iv`, `†`
     * @param int                           $scope      the run of numbering, from 0
     * @param non-empty-list<NoteReference> $references
     */
    public function __construct(
        public string $id,
        public string $html,
        public string $blockId,
        public int $number,
        public string $label,
        public int $scope,
        public array $references,
    ) {
    }

    /**
     * The index in the document of the top-level block that holds the first reference, where a section's notes are
     * counted from.
     */
    public function firstTopLevelIndex(): int
    {
        return $this->references[0]->topLevelIndex;
    }
}
