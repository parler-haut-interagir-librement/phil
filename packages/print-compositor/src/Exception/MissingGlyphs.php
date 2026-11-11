<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Exception;

use DomainException;
use Throwable;

/**
 * A text with characters its font does not cover: drawn, they would print as empty boxes or not at all.
 */
final class MissingGlyphs extends DomainException implements PrintCompositorException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
