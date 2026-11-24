<?php

declare(strict_types=1);

namespace Phil\SamlSp\Exception;

use RuntimeException;
use Throwable;

/**
 * An encrypted assertion that cannot be decrypted with the keys of the service provider, or with a refused algorithm.
 */
final class DecryptionFailed extends RuntimeException implements SamlException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
