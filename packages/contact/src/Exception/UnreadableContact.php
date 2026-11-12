<?php

declare(strict_types=1);

namespace Phil\Contact\Exception;

use UnexpectedValueException;

/**
 * A stored card that cannot be read back.
 */
final class UnreadableContact extends UnexpectedValueException implements ContactException
{
    public static function because(string $reason): self
    {
        return new self(\sprintf('The stored contact card cannot be read: %s.', rtrim($reason, '.')));
    }
}
