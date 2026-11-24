<?php

declare(strict_types=1);

namespace Phil\SamlSp\Exception;

use RuntimeException;
use Throwable;

/**
 * A certificate or a private key that cannot be read, that does not match, or whose RSA key is too short.
 */
final class InvalidKey extends RuntimeException implements SamlException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
