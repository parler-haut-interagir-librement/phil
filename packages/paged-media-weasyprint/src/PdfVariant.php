<?php

declare(strict_types=1);

namespace Phil\PagedMediaWeasyprint;

/**
 * The standard a PDF is written to, under the name WeasyPrint gives it.
 */
enum PdfVariant: string
{
    case PdfA1a = 'pdf/a-1a';
    case PdfA1b = 'pdf/a-1b';
    case PdfA2a = 'pdf/a-2a';
    case PdfA2b = 'pdf/a-2b';
    case PdfA2u = 'pdf/a-2u';
    case PdfA3a = 'pdf/a-3a';
    case PdfA3b = 'pdf/a-3b';
    case PdfA3u = 'pdf/a-3u';
    case PdfA4e = 'pdf/a-4e';
    case PdfA4f = 'pdf/a-4f';
    case PdfA4u = 'pdf/a-4u';
    case PdfUa1 = 'pdf/ua-1';
    case PdfUa2 = 'pdf/ua-2';
    case PdfX1a = 'pdf/x-1a';
    case PdfX3 = 'pdf/x-3';
    case PdfX4 = 'pdf/x-4';
    case PdfX5g = 'pdf/x-5g';

    /**
     * Whether it is a PDF/X standard, for print, which needs the ICC profile of its printing condition.
     */
    public function isPdfX(): bool
    {
        return str_starts_with($this->value, 'pdf/x-');
    }
}
