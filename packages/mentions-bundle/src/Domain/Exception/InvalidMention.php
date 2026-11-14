<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A handle, a pattern or a limit that a mention cannot be made of.
 */
final class InvalidMention extends InvalidArgumentException implements MentionsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
