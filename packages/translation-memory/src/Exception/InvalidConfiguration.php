<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Exception;

use InvalidArgumentException;

/**
 * A service of the library built with a setting out of its range.
 */
final class InvalidConfiguration extends InvalidArgumentException implements TranslationMemoryException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
