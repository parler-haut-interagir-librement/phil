<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value a model or a setting cannot take: an empty name, an address out of shape, a lifetime below one second. The
 * message says which rule was broken, never a secret.
 */
final class InvalidValue extends InvalidArgumentException implements IdentityProviderException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
