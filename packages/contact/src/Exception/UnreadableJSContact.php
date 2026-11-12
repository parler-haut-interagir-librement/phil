<?php

declare(strict_types=1);

namespace Phil\Contact\Exception;

use UnexpectedValueException;

/**
 * A JSContact card that cannot be read: too long, too deep, no JSON, or not shaped as RFC 9553 says.
 */
final class UnreadableJSContact extends UnexpectedValueException implements ContactException
{
    public static function because(string $reason): self
    {
        return new self(\sprintf('The JSContact card cannot be read: %s.', rtrim($reason, '.')));
    }
}
