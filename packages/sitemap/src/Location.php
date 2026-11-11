<?php

declare(strict_types=1);

namespace Phil\Sitemap;

/**
 * The addresses a sitemap gives: absolute, over HTTP or HTTPS, 2048 characters at most.
 *
 * @internal
 */
final class Location
{
    public const int MAX_LENGTH = 2048;

    public static function check(string $location, string $what): string
    {
        $scheme = strtolower((string) parse_url($location, \PHP_URL_SCHEME));

        if (\strlen($location) > self::MAX_LENGTH || false === filter_var($location, \FILTER_VALIDATE_URL) || !\in_array($scheme, ['http', 'https'], true)) {
            throw InvalidSitemap::because(\sprintf('%s is an absolute HTTP or HTTPS URL of %d characters at most; "%s" is not.', $what, self::MAX_LENGTH, $location));
        }

        return $location;
    }
}
