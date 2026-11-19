<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value a token cannot take: an empty or too long owner or name, a prefix out of shape, an expiry before the token
 * was created or past the longest lifetime, a scope given twice.
 */
final class InvalidTokenValue extends InvalidArgumentException implements ApiKeysException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
