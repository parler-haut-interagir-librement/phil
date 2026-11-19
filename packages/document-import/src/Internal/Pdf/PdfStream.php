<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

/**
 * A stream of a PDF file: its dictionary and its bytes as they are in the file, still encoded by its filters.
 *
 * @internal
 */
final readonly class PdfStream
{
    public function __construct(public PdfDictionary $dictionary, public string $raw)
    {
    }
}
