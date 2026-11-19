<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Application;

use Phil\DocumentImport\Format;

/**
 * A file waiting for its import: its bytes, the name it came with and its format, detected when it was accepted.
 */
final readonly class PendingImport
{
    public function __construct(
        public string $contents,
        public Format $format,
        public ?string $filename = null,
    ) {
    }
}
