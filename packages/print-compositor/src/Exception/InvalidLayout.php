<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A layout that cannot be composed: a size or a resolution that is not positive, a box outside its zone, a zone the
 * photo does not place, a colour or a quality out of range.
 */
final class InvalidLayout extends InvalidArgumentException implements PrintCompositorException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
