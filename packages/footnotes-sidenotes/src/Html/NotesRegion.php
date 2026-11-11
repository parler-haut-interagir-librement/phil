<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Html;

use Override;
use Phil\FootnotesSidenotes\Note;
use Phil\Ujo\Block;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\Rendering;

/**
 * Writes the regions of notes, placed in the document as blocks of their own so that a block reading the whole document
 * — a table of contents — still sees it entire.
 *
 * @internal
 */
final readonly class NotesRegion implements BlockRenderer
{
    public const string TYPE = 'footnotesSidenotesRegion';

    /**
     * @param array<string, list<Note>> $regions the notes of each region block, by its identifier
     */
    public function __construct(
        private NoteWriter $writer,
        private array $regions,
        private int $headingLevel,
    ) {
    }

    #[Override]
    public function render(Block $block, Rendering $rendering): string
    {
        return $this->writer->region($block->id, $this->regions[$block->id], $this->headingLevel);
    }
}
