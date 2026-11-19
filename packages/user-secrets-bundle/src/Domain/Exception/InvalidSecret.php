<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * An owner, a name, a value or a credential the bundle cannot keep: the message says which and why.
 */
final class InvalidSecret extends InvalidArgumentException implements UserSecretsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
