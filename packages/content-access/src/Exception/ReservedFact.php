<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Exception;

use InvalidArgumentException;

/**
 * A fact an application passes under a name the library sets itself, such as `viewer.id` or `now`: refused, so that
 * no caller can stand in for the viewer or the clock.
 */
final class ReservedFact extends InvalidArgumentException implements ContentAccessException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('The fact "%s" is set by the library from the viewer and the clock; pass another name.', $name));
    }
}
