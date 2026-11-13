<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * An anchor, a body, a suggestion or a change of state that a thread or a comment cannot be made of.
 */
final class InvalidComment extends InvalidArgumentException implements CommentsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
