<?php

declare(strict_types=1);

namespace Phil\Highlight\Exception;

use InvalidArgumentException;

/**
 * A language was added under a name that cannot be asked for: empty, with upper case, or with characters outside
 * letters, digits and `_ + # . -`.
 */
final class InvalidLanguage extends InvalidArgumentException implements HighlightException
{
    public static function name(string $name): self
    {
        return new self(\sprintf('"%s" cannot name a language: write lower-case letters, digits and "_ + # . -", 32 at most.', $name));
    }
}
