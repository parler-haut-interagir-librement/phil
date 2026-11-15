<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Exception;

use RuntimeException;

/**
 * A feed file that could not be written; the file already in place, if any, is left as it was.
 */
final class FeedNotWritten extends RuntimeException implements ProductFeedException
{
    public static function directory(string $directory): self
    {
        return new self(\sprintf('The feed is written in an existing, writable directory; "%s" is not one.', $directory));
    }

    public static function temporary(string $directory): self
    {
        return new self(\sprintf('No temporary file could be created in "%s".', $directory));
    }

    public static function write(string $path): self
    {
        return new self(\sprintf('The feed could not be written to "%s".', $path));
    }

    public static function rename(string $temporary, string $path): self
    {
        return new self(\sprintf('The feed written to "%s" could not be moved to "%s".', $temporary, $path));
    }

    public static function tooManyItems(int $maximum): self
    {
        return new self(\sprintf('A feed holds %d items at most: the source gave more.', $maximum));
    }
}
