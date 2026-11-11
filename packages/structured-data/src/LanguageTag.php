<?php

declare(strict_types=1);

namespace Phil\StructuredData;

use Phil\StructuredData\Exception\InvalidStructuredData;

/**
 * A language tag as BCP 47 writes it: a language, then maybe a script, a region and variants. A locale written with
 * underscores, as Symfony and ICU write them, is read too: `fr_CA` is `fr-CA`.
 */
final class LanguageTag
{
    private const string PATTERN = '/^(?<language>[a-z]{2,3})(?:-(?<script>[a-z]{4}))?(?:-(?<region>[a-z]{2}|\d{3}))?(?<variants>(?:-(?:[a-z\d]{5,8}|\d[a-z\d]{3}))*)$/Di';

    /**
     * The tag in its usual case — language in lower case, script in title case, region in upper case.
     *
     * @throws InvalidStructuredData when the text is no language tag
     */
    public static function normalize(string $tag): string
    {
        if (1 !== preg_match(self::PATTERN, str_replace('_', '-', $tag), $parts)) {
            throw InvalidStructuredData::because(\sprintf('A language is a BCP 47 tag, such as "fr" or "fr-CA"; "%s" is not.', $tag));
        }

        return strtolower($parts['language'])
            .('' === $parts['script'] ? '' : '-'.ucfirst(strtolower($parts['script'])))
            .('' === $parts['region'] ? '' : '-'.strtoupper($parts['region']))
            .strtolower($parts['variants']);
    }

    public static function isValid(string $tag): bool
    {
        return 1 === preg_match(self::PATTERN, $tag);
    }
}
