<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Contract;

use Phil\PagedMediaBundle\Application\PrintableHtml;

/**
 * The paged-media engine of the application, which lays HTML and CSS out as a PDF: WeasyPrint, Prince or another one.
 */
interface PrintEngine
{
    /**
     * @return string the bytes of the PDF
     */
    public function pdf(PrintableHtml $page): string;
}
