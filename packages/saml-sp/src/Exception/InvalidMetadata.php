<?php

declare(strict_types=1);

namespace Phil\SamlSp\Exception;

use RuntimeException;
use Throwable;

/**
 * Metadata refused: malformed, without an identity provider, without a signing certificate, expired, or with a signature that does not verify.
 */
final class InvalidMetadata extends RuntimeException implements SamlException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
