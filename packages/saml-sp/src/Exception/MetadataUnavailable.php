<?php

declare(strict_types=1);

namespace Phil\SamlSp\Exception;

use RuntimeException;
use Throwable;

/**
 * Metadata that cannot be fetched: refused address, timeout, error status or too large.
 */
final class MetadataUnavailable extends RuntimeException implements SamlException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
