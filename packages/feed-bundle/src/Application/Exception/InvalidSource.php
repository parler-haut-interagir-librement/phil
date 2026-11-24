<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Application\Exception;

use LogicException;

/**
 * A source the bundle cannot serve: its name is not one an address holds, or another source has it.
 */
final class InvalidSource extends LogicException
{
    public static function name(string $name, string $class): self
    {
        return new self(\sprintf('The feed source %s is named "%s": a name is 1 to 64 lowercase letters, digits, hyphens or underscores, starting with a letter or a digit.', $class, mb_substr($name, 0, 64)));
    }

    public static function duplicate(string $name, string $class, string $other): self
    {
        return new self(\sprintf('The feed sources %s and %s are both named "%s".', $other, $class, $name));
    }
}
