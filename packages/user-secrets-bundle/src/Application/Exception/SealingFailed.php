<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Exception;

use Phil\UserSecretsBundle\Domain\Exception\UserSecretsException;
use RuntimeException;
use Throwable;

/**
 * A value the KMS did not seal: unreachable, refusing, or failing otherwise; the previous exception says why.
 */
final class SealingFailed extends RuntimeException implements UserSecretsException
{
    public static function kms(string $keyId, Throwable $previous): self
    {
        return new self(\sprintf('The KMS did not seal a value under the master key "%s": %s', $keyId, $previous->getMessage()), previous: $previous);
    }
}
