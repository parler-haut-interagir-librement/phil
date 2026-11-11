<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * Bytes or a file that GD cannot decode as an image, or an image larger than the pixel limit given.
 */
final class InvalidImage extends InvalidArgumentException implements PrintCompositorException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
