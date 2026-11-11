<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Infrastructure\Purger;

use RuntimeException;
use Throwable;

/**
 * A cache in front did not accept a purge: its responses are stale until they expire. One failure says which caches
 * failed, and why; the others were purged all the same.
 */
final class PurgeFailed extends RuntimeException
{
    public static function answered(string $cache, int $status): self
    {
        return new self(self::answer($cache, $status));
    }

    public static function answer(string $cache, int $status): string
    {
        return \sprintf('%s answered the purge with %d.', $cache, $status);
    }

    public static function unreachable(string $cache, Throwable $error): string
    {
        return \sprintf('%s could not be reached: %s.', $cache, rtrim($error->getMessage(), '.'));
    }

    /**
     * @param non-empty-list<string> $failures one sentence each
     */
    public static function of(array $failures, ?Throwable $previous = null): self
    {
        return new self(implode(' ', $failures), previous: $previous);
    }
}
