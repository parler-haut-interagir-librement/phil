<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes;

/**
 * Where the notes of a document are written, chosen by the caller of each rendering.
 */
enum Placement: string
{
    /** In one labelled region after the last block. */
    case EndOfDocument = 'end-of-document';

    /** In a labelled region at the end of each section that references a note. */
    case EndOfSection = 'end-of-section';

    /** Beside the text, in a margin column on wide viewports and behind an inline toggle on narrow ones. */
    case Margin = 'margin';

    /** At the foot of the printed page, through `float: footnote`. */
    case PageFoot = 'page-foot';

    /**
     * Whether the text of a note is written where it is first referenced, rather than in a list of notes.
     */
    public function isInFlow(): bool
    {
        return self::Margin === $this || self::PageFoot === $this;
    }
}
