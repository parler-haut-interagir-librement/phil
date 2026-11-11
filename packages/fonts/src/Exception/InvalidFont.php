<?php

declare(strict_types=1);

namespace Phil\Fonts\Exception;

use InvalidArgumentException;

/**
 * A file that is not a font the library can read: unknown signature, truncated, a table out of bounds, corrupt compressed data.
 */
final class InvalidFont extends InvalidArgumentException implements FontsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
