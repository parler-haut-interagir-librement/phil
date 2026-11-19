<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Domain\Event;

use Phil\DocumentImport\Exception\DocumentImportException;

/**
 * A file imported on a worker and refused — not what its format says, larger than the limits, refused by the
 * antivirus —, by its claim, for the listeners that tell the author why.
 */
final readonly class DocumentImportFailed
{
    public function __construct(
        public string $claim,
        public DocumentImportException $reason,
    ) {
    }
}
