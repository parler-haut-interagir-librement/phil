<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Exception;

use InvalidArgumentException;

/**
 * A segment that cannot be built or cannot take the change asked of it.
 */
final class InvalidSegment extends InvalidArgumentException implements TranslationMemoryException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
