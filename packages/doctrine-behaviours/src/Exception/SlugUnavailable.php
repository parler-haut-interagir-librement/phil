<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Exception;

use RuntimeException;

/**
 * Every numbered form of a slug is taken.
 */
final class SlugUnavailable extends RuntimeException
{
    public static function of(string $slug, string $table, int $attempts): self
    {
        return new self(\sprintf('No slug is free for "%s" in the table %s after %d attempts: give the record another title or slug.', $slug, $table, $attempts));
    }
}
