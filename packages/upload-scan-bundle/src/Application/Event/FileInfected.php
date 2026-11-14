<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application\Event;

/**
 * A file was found infected and refused. The name is the one the sender gave the file, never to be trusted as a path;
 * the context says where it came from: `upload`, `media`, `document_import`, `rescan.<target>`, or the application's
 * own.
 */
final readonly class FileInfected
{
    /**
     * @param string|null $quarantineId the identifier of the file in the quarantine, `null` when it was not kept
     */
    public function __construct(
        public string $name,
        public string $signature,
        public string $context,
        public ?string $quarantineId = null,
    ) {
    }
}
