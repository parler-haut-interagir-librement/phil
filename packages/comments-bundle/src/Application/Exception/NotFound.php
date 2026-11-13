<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Application\Exception;

use Phil\CommentsBundle\Domain\Exception\CommentsException;
use RuntimeException;

/**
 * No thread or comment has this identifier.
 */
final class NotFound extends RuntimeException implements CommentsException
{
    public static function thread(string $id): self
    {
        return new self(\sprintf('There is no thread "%s".', $id));
    }

    public static function comment(string $id): self
    {
        return new self(\sprintf('There is no comment "%s".', $id));
    }
}
