<?php

declare(strict_types=1);

namespace Phil\Epub\Exception;

use InvalidArgumentException;

/**
 * A book that cannot be written as it is described: an empty title, a language that is not a language tag, a path
 * used twice or outside the package, a media type outside the EPUB core media types, a book without chapter.
 */
final class InvalidBook extends InvalidArgumentException implements EpubException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
