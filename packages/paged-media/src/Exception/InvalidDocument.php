<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Exception;

use InvalidArgumentException;

/**
 * A document built in a way that cannot be written: text before any page, a size or a measure out of range.
 */
final class InvalidDocument extends InvalidArgumentException implements PagedMediaException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
