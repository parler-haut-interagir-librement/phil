<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

/**
 * A bare word of a PDF file: a keyword such as `obj`, `stream` or `R`, or an operator of a content stream.
 *
 * @internal
 */
final readonly class PdfKeyword
{
    public function __construct(public string $word)
    {
    }
}
