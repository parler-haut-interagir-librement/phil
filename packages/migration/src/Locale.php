<?php

declare(strict_types=1);

namespace Phil\Migration;

/**
 * The locales announcements are written in: a language, and maybe a region — "fr" or "fr_CA".
 *
 * @internal
 */
final class Locale
{
    public static function check(string $locale): string
    {
        if (1 !== preg_match('/^[a-z]{2,3}(?:_[A-Z]{2})?$/D', $locale)) {
            throw InvalidAnnouncement::because(\sprintf('A locale is a language, and maybe a region: "fr" or "fr_CA"; "%s" is not.', $locale));
        }

        return $locale;
    }
}
