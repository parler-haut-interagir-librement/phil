<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\Exception;

use RuntimeException;

/**
 * A secret its owner revoked: its value was wiped and nothing can read it until the owner stores a new one.
 */
final class RevokedSecret extends RuntimeException implements UserSecretsException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('The secret "%s" was revoked: store a new value to use it again.', $name));
    }
}
