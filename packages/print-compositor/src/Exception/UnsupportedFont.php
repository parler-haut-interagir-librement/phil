<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A font file that cannot be read, or that FreeType through GD cannot draw: a WOFF, a WOFF2 or a collection.
 */
final class UnsupportedFont extends InvalidArgumentException implements PrintCompositorException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
