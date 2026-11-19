<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Exception;

use Phil\UserSecretsBundle\Domain\Exception\UserSecretsException;
use RuntimeException;

/**
 * The owner has no secret of that name.
 */
final class SecretNotFound extends RuntimeException implements UserSecretsException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('The owner has no secret "%s".', $name));
    }
}
