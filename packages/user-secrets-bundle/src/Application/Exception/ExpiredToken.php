<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Exception;

use Phil\UserSecretsBundle\Domain\Exception\UserSecretsException;
use RuntimeException;

/**
 * An OAuth token that has expired and cannot be refreshed — it has no refresh token, or no refresher serves its
 * service: the owner must connect the service again.
 */
final class ExpiredToken extends RuntimeException implements UserSecretsException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('The OAuth token "%s" has expired and cannot be refreshed: the owner must connect the service again.', $name));
    }
}
