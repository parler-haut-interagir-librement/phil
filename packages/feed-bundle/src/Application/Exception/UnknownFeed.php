<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Application\Exception;

use RuntimeException;

/**
 * No source has that name.
 */
final class UnknownFeed extends RuntimeException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('No feed is named "%s".', mb_substr($name, 0, 64)));
    }
}
