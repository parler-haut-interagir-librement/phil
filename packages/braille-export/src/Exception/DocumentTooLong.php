<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Exception;

use RuntimeException;

/**
 * A document that would fill more braille pages than the page format allows.
 */
final class DocumentTooLong extends RuntimeException implements BrailleExportException
{
    public static function over(int $maxPages): self
    {
        return new self(\sprintf('The document fills more than the %d braille pages allowed.', $maxPages));
    }
}
