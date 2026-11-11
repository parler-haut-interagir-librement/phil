<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Exception;

use InvalidArgumentException;

/**
 * A document description that cannot be checked: a size that is not positive, an image without pixels, an unknown
 * colour space.
 */
final class InvalidDocument extends InvalidArgumentException implements PrintPreflightException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
