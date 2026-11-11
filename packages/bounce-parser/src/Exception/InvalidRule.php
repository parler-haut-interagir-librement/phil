<?php

declare(strict_types=1);

namespace Phil\BounceParser\Exception;

use InvalidArgumentException;

/**
 * A rule whose pattern is not a valid regular expression or looks for nothing, on a header field it does not name,
 * whose name is taken, or placed next to a rule that does not exist.
 */
final class InvalidRule extends InvalidArgumentException implements BounceParserException
{
    public static function pattern(string $name): self
    {
        return new self(\sprintf('The pattern of the rule "%s" is not a valid regular expression.', $name));
    }

    public static function duplicate(string $name): self
    {
        return new self(\sprintf('A rule is already named "%s".', $name));
    }

    public static function unknown(string $name): self
    {
        return new self(\sprintf('No rule is named "%s".', $name));
    }

    public static function header(string $name): self
    {
        return new self(\sprintf('The rule "%s" looks at a header field without naming it.', $name));
    }

    public static function emptyNeedle(string $name): self
    {
        return new self(\sprintf('The rule "%s" looks for an empty text.', $name));
    }
}
