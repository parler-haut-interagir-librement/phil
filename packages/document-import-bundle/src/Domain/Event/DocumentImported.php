<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Domain\Event;

use Phil\DocumentImport\Imported;

/**
 * A file imported by `DocumentImports`: the result, its report of what was left out, the file name it came with, and
 * the claim of a file imported on a worker, for the listeners that keep the document and its report, tell the author,
 * or count the omissions.
 */
final readonly class DocumentImported
{
    public function __construct(
        public Imported $imported,
        public ?string $filename = null,
        public ?string $claim = null,
    ) {
    }
}
