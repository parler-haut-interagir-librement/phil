<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Internal;

use Phil\PagedMedia\Gotenberg\Exception\InvalidRequest;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url as WebAddress;
use Uri\WhatWg\Url as WhatWgUrl;

/**
 * The addresses the client reads, in two kinds.
 *
 * A public address — a file Gotenberg downloads, a timestamp authority — is read by `phil/url`: absolute, `http` or
 * `https`, a host name of labels joined by dots, no credentials, no IP address. Gotenberg fetches it from inside the
 * network it runs in, so a bare name or an IP address would let a document reach a service that is not public.
 *
 * A service address — Gotenberg itself, the webhook it calls back — names a host of the application's own network,
 * often a bare name such as `gotenberg`, which `phil/url` refuses by design. It is read by the WHATWG URL parser that
 * `phil/url` rests on, and must be absolute, `http` or `https`, with a host and without credentials, which go in
 * `BasicAuth`.
 *
 * @internal
 */
final class Url
{
    /**
     * A service address of the application's network.
     */
    public static function http(string $url, string $what): string
    {
        // The authority runs to the first `/`, `?` or `#`; an `@` in it would carry credentials. The parser refuses an
        // empty host, a port out of range and a host it cannot read.
        $parsed = 1 === preg_match('#\Ahttps?://[^/\\\\?\#@\s]+(?:[/?\#]\S*)?\z#i', $url) ? WhatWgUrl::parse($url) : null;

        if (!$parsed instanceof WhatWgUrl) {
            throw InvalidRequest::because(\sprintf('%s is an absolute URL with an http or https scheme, a host and no credentials; "%s" is not.', $what, $url));
        }

        return $url;
    }

    /**
     * A public address, which Gotenberg fetches.
     */
    public static function public(string $url, string $what): string
    {
        try {
            WebAddress::fromString($url);
        } catch (InvalidUrl $invalidUrl) {
            throw InvalidRequest::because(\sprintf('%s is a public http or https address; "%s" is not. %s', $what, $url, $invalidUrl->getMessage()));
        }

        return $url;
    }

    /**
     * A value that goes into a header or a file name: no line break, which would end the header.
     */
    public static function headerSafe(string $value, string $what): string
    {
        if ('' === $value || false !== strpbrk($value, "\r\n\0")) {
            throw InvalidRequest::because(\sprintf('%s is a non-empty text without a line break.', $what));
        }

        return $value;
    }
}
