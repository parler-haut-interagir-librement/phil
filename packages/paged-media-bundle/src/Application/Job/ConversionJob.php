<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Job;

use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\Pipeline;

/**
 * What a worker needs to convert office documents with LibreOffice: the documents, the conversion's options, the
 * stages, the signature and the timestamp asked for, and where the file is kept. It is kept by the job store; the
 * `ConvertDocuments` message carries only its identifier.
 */
final readonly class ConversionJob
{
    public function __construct(
        public Files $documents,
        public string $path,
        public LibreOfficeOptions $options = new LibreOfficeOptions(),
        public ?Pipeline $pipeline = null,
        public bool $sign = false,
        public bool $timestamp = false,
    ) {
    }
}
