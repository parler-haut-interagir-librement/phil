<?php

declare(strict_types=1);

namespace Phil\Address\Exception;

use InvalidArgumentException;

/**
 * A country code or a field that cannot be part of an address.
 */
final class InvalidAddress extends InvalidArgumentException implements AddressException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
