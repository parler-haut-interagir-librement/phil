<?php

declare(strict_types=1);

namespace Phil\SocialCards\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A value of the share metadata of a page that the tags cannot carry: an address that is not an absolute HTTP or HTTPS
 * URL, a locale, a handle or a media type that is not one, an empty title.
 */
final class InvalidShareMetadata extends InvalidArgumentException implements SocialCardsException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
