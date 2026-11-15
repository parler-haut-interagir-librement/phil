<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * Bytes or text a reader could not read: a CBOR item out of bounds, a JSON Web Token in the wrong shape, a base64url
 * text with a character it cannot hold. The message says what was wrong, never the input itself.
 */
final class MalformedInput extends InvalidArgumentException implements IdentityProviderException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
