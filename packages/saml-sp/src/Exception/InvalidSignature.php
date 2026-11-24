<?php

declare(strict_types=1);

namespace Phil\SamlSp\Exception;

use RuntimeException;
use Throwable;

/**
 * A signature that is missing, malformed, made with a refused algorithm, does not cover the element read, or does not verify with the trusted certificates.
 */
final class InvalidSignature extends RuntimeException implements SamlException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
