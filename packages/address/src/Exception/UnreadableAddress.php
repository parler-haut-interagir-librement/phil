<?php

declare(strict_types=1);

namespace Phil\Address\Exception;

use UnexpectedValueException;

/**
 * A stored address that cannot be read back.
 */
final class UnreadableAddress extends UnexpectedValueException implements AddressException
{
    public static function because(string $reason): self
    {
        return new self(\sprintf('The stored address cannot be read: %s.', rtrim($reason, '.')));
    }
}
