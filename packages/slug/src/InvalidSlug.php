<?php

declare(strict_types=1);

namespace Phil\Slug;

use InvalidArgumentException;

/**
 * A text that is not a slug, or one no slug can be made of.
 */
final class InvalidSlug extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
