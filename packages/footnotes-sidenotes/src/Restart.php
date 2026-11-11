<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes;

/**
 * Where the numbering of notes starts again at the first value.
 */
enum Restart: string
{
    /** One numbering for the whole document. */
    case Document = 'document';

    /** Again at each `header` block of the chapter level or above. */
    case Chapter = 'chapter';

    /** Again at each page marker, a `pageBreak` block by default. */
    case PageMarker = 'page-marker';
}
