<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Exception;

use InvalidArgumentException;

/**
 * A glossary term that cannot be built.
 */
final class InvalidTerm extends InvalidArgumentException implements TranslationMemoryException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
