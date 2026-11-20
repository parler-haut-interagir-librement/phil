<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Equivalence;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;

/**
 * A language tag as the groups of equivalent courses keep it: the shape of BCP 47 — a primary language of 2 to 8
 * letters, then subtags of 1 to 8 letters or digits, separated by `-` or `_` —, at most 35 characters, in lowercase
 * with `-` between its subtags, so that `fr_CA`, `fr-CA` and `FR-ca` name one language.
 */
final class LanguageTag
{
    public const int MAX_LENGTH = 35;

    private const string PATTERN = '/^[a-z]{2,8}(?:-[a-z0-9]{1,8})*$/D';

    private function __construct()
    {
    }

    /**
     * @throws InvalidCourse when the value is not a language tag
     */
    public static function normalize(string $tag): string
    {
        $tag = strtolower(str_replace('_', '-', trim($tag)));

        if (\strlen($tag) > self::MAX_LENGTH || 1 !== preg_match(self::PATTERN, $tag)) {
            throw InvalidCourse::because(\sprintf('A language is a tag such as "en" or "fr-CA", of %d characters at most.', self::MAX_LENGTH));
        }

        return $tag;
    }

    /**
     * The primary language of a normalized tag: `fr` for `fr-ca`.
     */
    public static function primary(string $tag): string
    {
        return explode('-', $tag, 2)[0];
    }
}
