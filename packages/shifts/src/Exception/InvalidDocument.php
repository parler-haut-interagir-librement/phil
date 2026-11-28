<?php

declare(strict_types=1);

namespace Phil\Shifts\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A document that cannot be read: not JSON, too large or too deep, of another format, or with a field of the wrong
 * shape. The message names the path of the field, never its value.
 */
final class InvalidDocument extends InvalidArgumentException implements ShiftsException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }

    public static function at(string $path, string $reason, ?Throwable $previous = null): self
    {
        return new self(\sprintf('%s: %s', $path, $reason), 0, $previous);
    }
}
