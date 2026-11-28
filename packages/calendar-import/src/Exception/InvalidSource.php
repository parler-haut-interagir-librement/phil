<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A source that cannot be read from: an identifier that is empty, too long or holds a control character, an address
 * `phil/url` refuses, a time zone PHP does not know, an override or a stored state that does not hold together.
 */
final class InvalidSource extends InvalidArgumentException implements CalendarImportException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
