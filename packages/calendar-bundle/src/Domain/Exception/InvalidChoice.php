<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A choice of a member's calendar that cannot be: a value its source does not name, or one choice too many.
 */
final class InvalidChoice extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
