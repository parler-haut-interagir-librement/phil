<?php

declare(strict_types=1);

namespace Phil\OpeningHours\Exception;

use InvalidArgumentException;

/**
 * A time, a range, a period or a set of hours that cannot be.
 */
final class InvalidOpeningHours extends InvalidArgumentException implements OpeningHoursException
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
