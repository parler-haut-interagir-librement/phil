<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Exception;

use DomainException;
use Throwable;

/**
 * A text that does not fit its box, even at the smallest size it may take.
 */
final class TextDoesNotFit extends DomainException implements PrintCompositorException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
