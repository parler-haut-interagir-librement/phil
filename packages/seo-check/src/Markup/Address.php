<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Markup;

use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Uri\WhatWg\Url as WhatWgUrl;

/**
 * An address read in a page, as a browser reads it: resolved against the page's own by PHP's URI extension, then read
 * by phil/url. An address on `localhost` or under `.localhost` is accepted when the page is itself served there, as in
 * development and in tests.
 */
final class Address
{
    /** The longest attribute read as an address; a longer one is no address. */
    public const int MAX_LENGTH = 4096;

    private function __construct()
    {
    }

    /**
     * The absolute `http` or `https` address an attribute leads to, or null when it leads nowhere a reader can follow:
     * another scheme, a malformed address, credentials, an IP address.
     *
     * @param bool $keepFragment whether the fragment stays; dropped, two links to parts of one page are one address
     */
    public static function resolve(string $value, Url $base, bool $keepFragment = true): ?Url
    {
        $value = self::trim($value);

        if (\strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        $resolved = WhatWgUrl::parse($value, WhatWgUrl::parse($base->toString()));

        if (!$resolved instanceof WhatWgUrl) {
            return null;
        }

        if (!$keepFragment) {
            $resolved = $resolved->withFragment(null);
        }

        try {
            return Url::fromString($resolved->toAsciiString(), allowLocalhost: self::isLocal($base));
        } catch (InvalidUrl) {
            return null;
        }
    }

    /**
     * Whether an attribute is an absolute address, with its scheme, rather than one relative to the page.
     */
    public static function isAbsolute(string $value): bool
    {
        $value = self::trim($value);

        return \strlen($value) <= self::MAX_LENGTH && WhatWgUrl::parse($value) instanceof WhatWgUrl;
    }

    /**
     * The value without the ASCII whitespace HTML strips around an address.
     */
    public static function trim(string $value): string
    {
        return trim($value, " \t\n\r\f");
    }

    private static function isLocal(Url $base): bool
    {
        return 'localhost' === $base->host || str_ends_with($base->host, '.localhost');
    }
}
