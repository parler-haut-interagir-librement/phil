<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Exception;

use InvalidArgumentException;

/**
 * A value the library cannot work with: an alphabet, a format, a key, a batch, a redeemer or an address.
 */
final class InvalidConfiguration extends InvalidArgumentException implements AccessCodesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
