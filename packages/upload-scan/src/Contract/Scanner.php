<?php

declare(strict_types=1);

namespace Phil\UploadScan\Contract;

use Phil\UploadScan\Exception\InvalidArgument;
use Phil\UploadScan\ScanResult;

/**
 * The port of an antivirus: a file in, a `ScanResult` out. A scanner never throws for a file it could not scan — the
 * engine unreachable, too slow, the file unreadable or too large: it answers an error result, which `ScanPolicy` turns
 * into a decision. It keeps nothing between two scans.
 */
interface Scanner
{
    /**
     * Scans the whole stream: a seekable stream is rewound first, so that one read before, to sniff its type or hash
     * it, is never scanned from where that read stopped; one that cannot be rewound is an error result once read. The
     * stream is left open, read to where the scan stopped.
     *
     * @param resource $stream
     *
     * @throws InvalidArgument when the value is not an open stream
     */
    public function scanStream(mixed $stream): ScanResult;

    /**
     * Scans a file on a local path; a path that is not a readable file is an error result.
     */
    public function scanPath(string $path): ScanResult;
}
