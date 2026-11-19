<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Exception;

use Phil\UserSecretsBundle\Domain\Exception\UserSecretsException;
use RuntimeException;
use Throwable;

/**
 * A current key the KMS has no master key for.
 */
final class UnknownKey extends RuntimeException implements UserSecretsException
{
    public static function current(string $keyId, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The KMS has no master key "%s" to seal with: check phil_user_secrets.current_key.', $keyId), previous: $previous);
    }
}
