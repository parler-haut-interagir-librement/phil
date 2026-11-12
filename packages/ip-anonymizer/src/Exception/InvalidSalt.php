<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Exception;

use InvalidArgumentException;

/**
 * A salt, its identifier or the master key of a rotating salt cannot be used.
 */
final class InvalidSalt extends InvalidArgumentException implements IpAnonymizerException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
