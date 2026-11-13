<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf;

/**
 * A standard a document is written to conform to.
 */
enum Conformance: string
{
    /**
     * PDF/A-2u (ISO 19005-2, level U): a document for long-term archiving, which looks the same everywhere and whose
     * text can be extracted as Unicode. Every font is embedded, colours are defined by an sRGB output intent, and the
     * document describes itself in XMP.
     */
    case PdfA2u = 'PDF/A-2u';
}
