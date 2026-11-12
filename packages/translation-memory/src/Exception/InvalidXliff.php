<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Exception;

use InvalidArgumentException;

/**
 * An XLIFF file that cannot be read: not well-formed XML, a document type declared, or not XLIFF 1.2 or 2.x.
 */
final class InvalidXliff extends InvalidArgumentException implements TranslationMemoryException
{
    public static function because(string $reason): self
    {
        return new self('The XLIFF file cannot be read: '.$reason);
    }
}
