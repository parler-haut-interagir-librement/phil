<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Exception;

use InvalidArgumentException;

/**
 * A TMX file that cannot be read: not well-formed XML, a document type declared, not TMX 1.4, or too large.
 */
final class InvalidTmx extends InvalidArgumentException implements TranslationMemoryException
{
    public static function because(string $reason): self
    {
        return new self('The TMX file cannot be read: '.$reason);
    }
}
