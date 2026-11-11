<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Exception;

use RuntimeException;
use Throwable;

/**
 * A GD build without what a composition needs: FreeType for text, PNG or WebP support for the output.
 */
final class MissingCapability extends RuntimeException implements PrintCompositorException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
