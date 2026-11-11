<?php

declare(strict_types=1);

namespace Phil\Sitemap;

use InvalidArgumentException;

/**
 * A URL, an alternate, an image or a section a sitemap cannot hold as given.
 */
final class InvalidSitemap extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
