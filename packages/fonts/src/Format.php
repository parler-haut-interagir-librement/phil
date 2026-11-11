<?php

declare(strict_types=1);

namespace Phil\Fonts;

/**
 * The container of a font file; each value is the keyword of the CSS `format()` function.
 */
enum Format: string
{
    /** An `sfnt` with TrueType outlines, or with no outlines at all, usually `.ttf`. */
    case TrueType = 'truetype';

    /** An `sfnt` with PostScript (CFF) outlines, usually `.otf`. */
    case OpenType = 'opentype';

    /** A TrueType or OpenType collection, several faces sharing one file, usually `.ttc` or `.otc`. */
    case Collection = 'collection';

    /** WOFF 1.0: an `sfnt` whose tables are compressed one by one with zlib. */
    case Woff = 'woff';

    /** WOFF 2.0: an `sfnt` whose tables are compressed together with Brotli. */
    case Woff2 = 'woff2';
}
