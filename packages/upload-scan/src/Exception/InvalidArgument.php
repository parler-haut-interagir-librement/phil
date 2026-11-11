<?php

declare(strict_types=1);

namespace Phil\UploadScan\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a scanner configured with a limit below 1, an address that is not a socket, a stream
 * that is not one.
 */
final class InvalidArgument extends InvalidArgumentException implements UploadScanException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
