<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Infrastructure\UploadScan;

use Override;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImportBundle\Application\Contract\DocumentScreening;
use Phil\UploadScanBundle\Application\UploadScanner;

/**
 * The screening by the scanner of `phil/upload-scan-bundle`: the file inspected whole under the context
 * `document_import` — the antivirus looks inside the archive of a DOCX or an ODT —, refused unless its policy keeps
 * it. An infected file is quarantined by the scanner, and its events are dispatched there.
 */
final readonly class UploadScanScreening implements DocumentScreening
{
    public function __construct(
        private UploadScanner $scanner,
    ) {
    }

    #[Override]
    public function screen(string $contents, string $filename): void
    {
        $inspection = $this->scanner->inspectContents($contents, $filename, 'document_import');

        if ($inspection->infected()) {
            throw InvalidDocument::because('The document contains a threat and was refused.');
        }

        if (!$inspection->accepted()) {
            throw InvalidDocument::because('The document could not be checked for threats. Please try again later.');
        }
    }
}
