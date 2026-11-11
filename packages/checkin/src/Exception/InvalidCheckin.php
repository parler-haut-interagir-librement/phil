<?php

declare(strict_types=1);

namespace Phil\Checkin\Exception;

use InvalidArgumentException;

/**
 * An identifier, a code, a credential, a policy or a check-in was given a value it cannot work with.
 */
final class InvalidCheckin extends InvalidArgumentException implements CheckinException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
