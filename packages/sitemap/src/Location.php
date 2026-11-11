<?php

declare(strict_types=1);

namespace Phil\Sitemap;

use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url as WebAddress;

/**
 * The addresses a sitemap gives: absolute, over HTTP or HTTPS, 2048 characters at most, read as a browser reads them
 * by phil/url and written in their canonical form — scheme and host in lower case, an international host in ASCII,
 * the default port dropped, the segments `.` and `..` resolved. An address with a user name or a password is refused,
 * since `https://example.com@evil.example/` leads to evil.example; so is one whose host is an IP address or a single
 * name, which no search engine reaches.
 *
 * @internal
 */
final class Location
{
    public const int MAX_LENGTH = WebAddress::MAX_LENGTH;

    public static function check(string $location, string $what): string
    {
        try {
            return WebAddress::fromString($location)->toString();
        } catch (InvalidUrl) {
            throw InvalidSitemap::because(\sprintf('%s is an absolute HTTP or HTTPS URL of %d characters at most; "%s" is not.', $what, self::MAX_LENGTH, $location));
        }
    }
}
