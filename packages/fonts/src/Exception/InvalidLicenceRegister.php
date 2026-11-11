<?php

declare(strict_types=1);

namespace Phil\Fonts\Exception;

use InvalidArgumentException;

/**
 * A licence register, or one of its records, that cannot be read back: invalid JSON, a missing or mistyped field.
 */
final class InvalidLicenceRegister extends InvalidArgumentException implements FontsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
