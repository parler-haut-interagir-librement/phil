<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Report;

/**
 * A file kept in the export store: its opaque identifier, its name and media type, its size in bytes and its number
 * of rows.
 */
final readonly class StoredExport
{
    public function __construct(
        public string $id,
        public string $filename,
        public string $mediaType,
        public int $size,
        public int $rows,
    ) {
    }
}
