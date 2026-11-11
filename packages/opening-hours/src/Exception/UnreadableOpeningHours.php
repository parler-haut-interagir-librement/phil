<?php

declare(strict_types=1);

namespace Phil\OpeningHours\Exception;

use UnexpectedValueException;

/**
 * Stored hours that cannot be read back.
 */
final class UnreadableOpeningHours extends UnexpectedValueException implements OpeningHoursException
{
    public static function because(string $reason): self
    {
        return new self(\sprintf('The stored opening hours cannot be read: %s.', rtrim($reason, '.')));
    }
}
