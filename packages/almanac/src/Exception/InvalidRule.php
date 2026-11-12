<?php

declare(strict_types=1);

namespace Phil\Almanac\Exception;

use InvalidArgumentException;

/**
 * A rule that cannot be read, or a definition that cannot be.
 */
final class InvalidRule extends InvalidArgumentException implements AlmanacException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function unreadable(string $text): self
    {
        return new self(\sprintf('"%s" is not a rule the almanac reads.', mb_substr($text, 0, 100)));
    }
}
