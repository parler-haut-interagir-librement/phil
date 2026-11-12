<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Exception;

use InvalidArgumentException;

/**
 * A text or a byte string is not an IPv4 or IPv6 address the library accepts.
 */
final class InvalidIpAddress extends InvalidArgumentException implements IpAnonymizerException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
