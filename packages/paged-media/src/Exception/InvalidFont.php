<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Exception;

use InvalidArgumentException;

/**
 * A font file the library cannot read or may not embed.
 */
final class InvalidFont extends InvalidArgumentException implements PagedMediaException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
