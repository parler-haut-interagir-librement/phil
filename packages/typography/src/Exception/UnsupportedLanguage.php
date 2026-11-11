<?php

declare(strict_types=1);

namespace Phil\Typography\Exception;

use InvalidArgumentException;

/**
 * A locale or a pattern tag for which the library has no hyphenation patterns.
 */
final class UnsupportedLanguage extends InvalidArgumentException implements TypographyException
{
    public static function of(string $language): self
    {
        return new self(\sprintf('There are no hyphenation patterns for "%s".', $language));
    }
}
