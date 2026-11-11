<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes;

/**
 * One reference to a note in the text.
 */
final readonly class NoteReference
{
    /**
     * @param string $blockId       the block holding the reference
     * @param int    $occurrence    its rank among the references to the same note, from 1
     * @param int    $topLevelIndex the index in the document of the top-level block that holds it
     */
    public function __construct(
        public string $blockId,
        public int $occurrence,
        public int $topLevelIndex,
    ) {
    }
}
