<?php

declare(strict_types=1);

namespace Phil\SafeHttp;

use Phil\SafeHttp\Exception\RefusedAddress;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use Uri\WhatWg\Url as WhatWgUrl;

/**
 * The first check of every address the client reads, before any name is resolved: an absolute HTTP or HTTPS address
 * as phil/url reads it — a host name, never an IP address in any of its spellings, no user name nor password, port 0
 * refused, 2,048 characters at most. A redirect's `Location` is resolved against the address that answered, as a
 * browser resolves it, then checked the same way.
 *
 * What a host name resolves to is checked by Symfony's `NoPrivateNetworkHttpClient`, at each request.
 */
final class AddressGuard
{
    public static function address(string $address): Url
    {
        try {
            return Url::fromString($address);
        } catch (UrlException $urlException) {
            throw RefusedAddress::notPublic($address, $urlException);
        }
    }

    public static function redirect(Url $from, string $location): Url
    {
        $resolved = \strlen($location) <= Url::MAX_LENGTH ? WhatWgUrl::parse($location, WhatWgUrl::parse($from->toString())) : null;

        if (!$resolved instanceof WhatWgUrl) {
            throw RefusedAddress::redirect($location);
        }

        try {
            return Url::fromString($resolved->toAsciiString());
        } catch (UrlException $urlException) {
            throw RefusedAddress::redirect($location, $urlException);
        }
    }
}
