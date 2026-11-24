<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Rule;

/**
 * How the rules compare language tags: BCP 47 tags compare without regard to case, and an underscore, as an
 * application's locale writes `fr_CA`, reads as a hyphen.
 *
 * @internal
 */
final class LanguageTag
{
    private function __construct()
    {
    }

    /**
     * The tag in lower case, its subtags separated by hyphens: `fr-ca` for `fr_CA`.
     */
    public static function normalize(string $tag): string
    {
        return strtolower(str_replace('_', '-', trim($tag)));
    }

    /**
     * The primary language of the tag, in lower case: `fr` for `fr-CA`.
     */
    public static function primary(string $tag): string
    {
        return preg_replace('/-.*/s', '', self::normalize($tag)) ?? '';
    }

    /**
     * Whether the text has the shape of a BCP 47 tag: a language of two or three letters, then subtags of one to eight
     * letters or digits, separated by hyphens, 35 characters at most.
     */
    public static function isValid(string $tag): bool
    {
        return \strlen($tag) <= 35 && 1 === preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{1,8})*$/D', $tag);
    }
}
