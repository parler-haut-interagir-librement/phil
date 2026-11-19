<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

/**
 * A name of a PDF file, such as `/Type`, without its slash and with its `#xx` escapes read.
 *
 * @internal
 */
final readonly class PdfName
{
    public function __construct(public string $name)
    {
    }
}
