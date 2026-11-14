<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application\Contract;

use Phil\UploadScan\ScanResult;
use Phil\UploadScanBundle\Application\Exception\QuarantineFailed;

/**
 * Where infected files are kept, out of reach of every visitor, for an operator to look at: never under a directory
 * the web server serves, never behind a route.
 */
interface Quarantine
{
    /**
     * Keeps the file of the stream, read from its current position, with its name and the result that condemned it.
     *
     * @param resource $stream
     *
     * @return non-empty-string the identifier of the file in the quarantine
     *
     * @throws QuarantineFailed when the file cannot be kept
     */
    public function isolate(mixed $stream, string $name, ScanResult $result): string;
}
