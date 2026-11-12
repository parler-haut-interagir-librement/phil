<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Exception;

use InvalidArgumentException;

/**
 * A language tag that is not a BCP 47 tag the library reads, or two languages that do not match.
 */
final class InvalidLanguage extends InvalidArgumentException implements TranslationMemoryException
{
    public static function tag(string $tag): self
    {
        return new self(\sprintf('"%s" is not a language tag such as "en", "fr-CA" or "zh-Hant-TW".', $tag));
    }

    public static function noParent(string $tag): self
    {
        return new self(\sprintf('"%s" is a primary language: a regional variant such as "fr-CA" has a parent to inherit from.', $tag));
    }

    public static function mismatch(string $what, string $expected, string $given): self
    {
        return new self(\sprintf('The %s language is "%s", but "%s" was given.', $what, $expected, $given));
    }
}
