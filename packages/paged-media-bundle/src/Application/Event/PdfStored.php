<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Event;

/**
 * Dispatched once a message's file is kept: the application learns which job it was, where the file is, and whether a
 * remediation left violations.
 */
final readonly class PdfStored
{
    /**
     * @param string $job the identifier `PagedMediaQueue` returned
     */
    public function __construct(
        public string $job,
        public string $path,
        public string $filename,
        public string $contentType,
        public ?int $residual = null,
        public ?int $rolledBack = null,
    ) {
    }
}
