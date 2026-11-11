<?php

declare(strict_types=1);

namespace Phil\Recurrence\Exception;

use InvalidArgumentException;

/**
 * A recurrence rule or a line of dates that cannot be, that RFC 5545 forbids, or that the library does not read.
 */
final class InvalidRule extends InvalidArgumentException implements RecurrenceException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function unreadable(string $input, string $what): self
    {
        return new self(\sprintf('"%s" is not %s.', mb_substr($input, 0, 100), $what));
    }
}
