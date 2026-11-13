<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Application\Exception;

use Phil\CommentsBundle\Domain\Exception\CommentsException;
use RuntimeException;

/**
 * The application's policy does not let this author do this.
 */
final class CommentRefused extends RuntimeException implements CommentsException
{
    public static function because(string $action): self
    {
        return new self(\sprintf('The policy does not allow to %s.', $action));
    }
}
