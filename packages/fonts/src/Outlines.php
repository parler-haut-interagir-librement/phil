<?php

declare(strict_types=1);

namespace Phil\Fonts;

/**
 * How the glyphs of a face are drawn, read from the tables it has.
 */
enum Outlines: string
{
    /** Quadratic outlines in a `glyf` table. */
    case TrueType = 'truetype';

    /** Cubic outlines in a `CFF ` or `CFF2` table. */
    case PostScript = 'postscript';

    /** Neither: bitmaps, colour images or SVG glyphs only. */
    case None = 'none';
}
