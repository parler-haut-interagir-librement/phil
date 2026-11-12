<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Exception;

use InvalidArgumentException;

/**
 * A TBX file that cannot be read: not well-formed XML, a document type declared, not TBX, or too large.
 */
final class InvalidTbx extends InvalidArgumentException implements TranslationMemoryException
{
    public static function because(string $reason): self
    {
        return new self('The TBX file cannot be read: '.$reason);
    }
}
