<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;

/**
 * The two fonts a printed template writes in: its regular text and what it stresses — a name, a price, a title.
 * Helvetica and Helvetica Bold by default; TrueType fonts for any script, and for a document that conforms to PDF/A.
 */
final readonly class TemplateFonts
{
    public function __construct(
        public StandardFont|TrueTypeFont $regular = StandardFont::Helvetica,
        public StandardFont|TrueTypeFont $bold = StandardFont::HelveticaBold,
    ) {
    }
}
