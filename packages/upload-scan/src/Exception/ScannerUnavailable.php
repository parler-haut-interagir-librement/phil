<?php

declare(strict_types=1);

namespace Phil\UploadScan\Exception;

use RuntimeException;

/**
 * The engine cannot be reached: thrown by a `ClamdConnector`, and turned into an error result by the scanner.
 */
final class ScannerUnavailable extends RuntimeException implements UploadScanException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
