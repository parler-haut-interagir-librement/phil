<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Application\Exception;

use InvalidArgumentException;
use Phil\Epub\Exception\EpubException;

/**
 * A font a book cannot carry: its licence forbids embedding, it may only be embedded as bitmaps, or the font directory
 * holds no readable file of it.
 */
final class FontNotEmbeddable extends InvalidArgumentException implements EpubException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
