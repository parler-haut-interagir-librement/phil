<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

use Phil\DocumentImport\Exception\LimitExceeded;

/**
 * The work a PDF may ask for, counted across its pages: each content operator run — those of a form XObject each time
 * the form is drawn —, and each run of text a link annotation is checked against. A file of a few kilobytes whose
 * forms draw one another, or whose thousands of links each cover the whole page, is refused instead of keeping the
 * reader busy for minutes.
 *
 * @internal
 */
final class PdfBudget
{
    private int $spent = 0;

    public function __construct(private readonly int $operations)
    {
    }

    /**
     * @throws LimitExceeded when the work of the document goes past the limit
     */
    public function spend(int $operations): void
    {
        $this->spent += $operations;

        if ($this->spent > $this->operations) {
            throw LimitExceeded::because(\sprintf('The PDF asks for more than %d operations to read its pages.', $this->operations));
        }
    }
}
