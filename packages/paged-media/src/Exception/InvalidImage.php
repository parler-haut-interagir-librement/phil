<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Exception;

use InvalidArgumentException;

/**
 * An image file the library cannot read.
 */
final class InvalidImage extends InvalidArgumentException implements PagedMediaException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
