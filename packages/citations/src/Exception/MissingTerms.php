<?php

declare(strict_types=1);

namespace Phil\Citations\Exception;

use InvalidArgumentException;

/**
 * The library holds no terms for a language: build a `Terms` with its words.
 */
final class MissingTerms extends InvalidArgumentException implements CitationsException
{
    public static function forLocale(string $locale): self
    {
        return new self(\sprintf('No terms for "%s": build a Terms with the words of its language.', $locale));
    }
}
