<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Domain\Exception;

use RuntimeException;

/**
 * A member already gave this code to the content: the store refused a second one, which two requests raced to add.
 */
final class AlreadyReacted extends RuntimeException implements ReactionsException
{
    public static function on(string $content, string $code): self
    {
        return new self(\sprintf('The member already reacted "%s" to "%s".', $code, $content));
    }
}
