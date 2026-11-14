<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application\Event;

/**
 * A file the scanner could not scan was accepted and flagged, as the policy `accept_and_flag` says: the application
 * keeps it out of sight until a `RescanFile` clears it.
 */
final readonly class FileFlagged
{
    public function __construct(
        public string $name,
        public string $reason,
        public string $context,
    ) {
    }
}
