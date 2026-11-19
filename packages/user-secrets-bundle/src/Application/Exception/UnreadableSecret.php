<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Exception;

use Phil\UserSecretsBundle\Domain\Exception\UserSecretsException;
use RuntimeException;
use Throwable;

/**
 * A ciphertext that does not open: altered, sealed under another key of the same identifier, moved to another row,
 * holding no credential, or one the KMS did not open — unreachable, refusing —, the KMS's exception kept as the
 * previous one.
 */
final class UnreadableSecret extends RuntimeException implements UserSecretsException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }

    public static function kms(string $keyId, Throwable $previous): self
    {
        return new self(\sprintf('The KMS did not open a sealed secret of the master key "%s": %s', $keyId, $previous->getMessage()), previous: $previous);
    }
}
