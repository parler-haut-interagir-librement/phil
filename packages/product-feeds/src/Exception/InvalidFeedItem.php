<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Exception;

use InvalidArgumentException;

/**
 * An item, a channel or a sale period that a feed cannot carry: a field missing, too long, not UTF-8, or at odds with
 * another.
 */
final class InvalidFeedItem extends InvalidArgumentException implements ProductFeedException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
