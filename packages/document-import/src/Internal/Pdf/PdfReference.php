<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

/**
 * A reference to an indirect object of a PDF file, `12 0 R`.
 *
 * @internal
 */
final readonly class PdfReference
{
    public function __construct(public int $number, public int $generation = 0)
    {
    }
}
