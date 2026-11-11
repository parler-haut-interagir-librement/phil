<?php

declare(strict_types=1);

namespace Phil\Ujo\Html;

/**
 * A language tag of BCP 47, checked for its form: a language of two or three letters and its extended subtags, or of
 * four to eight letters; a script, a region, variants, extensions and a private use part, in that order; or a private
 * use tag alone, `x-…`. Up to 64 characters. The subtags are not looked up in the IANA registry: `qq-ZZ` is
 * well-formed.
 */
final class LanguageTag
{
    /** The pattern of a well-formed tag. */
    public const string PATTERN = '/^(?:(?:[A-Za-z]{2,3}(?:-[A-Za-z]{3}){0,3}|[A-Za-z]{4,8})(?:-[A-Za-z]{4})?(?:-(?:[A-Za-z]{2}|\d{3}))?(?:-(?:[A-Za-z0-9]{5,8}|\d[A-Za-z0-9]{3}))*(?:-[0-9A-WYZa-wyz](?:-[A-Za-z0-9]{2,8})+)*(?:-[Xx](?:-[A-Za-z0-9]{1,8})+)?|[Xx](?:-[A-Za-z0-9]{1,8})+)$/D';

    /** The longest tag accepted, in characters. */
    public const int MAX_LENGTH = 64;

    public static function isValid(string $tag): bool
    {
        return \strlen($tag) <= self::MAX_LENGTH && 1 === preg_match(self::PATTERN, $tag);
    }
}
