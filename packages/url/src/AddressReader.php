<?php

declare(strict_types=1);

namespace Phil\Url;

use Phil\Url\Exception\InvalidUrl;
use Uri\WhatWg\Url as WhatWgUrl;

/**
 * What `Url` and `ServiceUrl` check alike, before the host: the text of an absolute `http` or `https` address, 2048
 * characters at most, without whitespace, user name, password nor port 0, parsed by PHP's URI extension.
 *
 * The patterns name their bytes: none depends on the locale of the process, whose `LC_CTYPE` would otherwise give PCRE
 * its character classes, a non-breaking space byte among the spaces.
 *
 * @internal
 */
final class AddressReader
{
    public static function parse(string $text): WhatWgUrl
    {
        $text = trim($text);

        if (\strlen($text) > Url::MAX_LENGTH) {
            throw InvalidUrl::tooLong(\strlen($text));
        }

        // The host follows the two slashes at once: `https:///path` and `https://\host`, which a browser would still open,
        // are refused. Whitespace is ASCII whitespace, whatever the locale.
        if (1 !== preg_match('#^[Hh][Tt][Tt][Pp][Ss]?://[^/\\\\]#', $text) || 1 === preg_match('/[\t\n\x0B\f\r ]/', $text)) {
            throw InvalidUrl::notAUrl($text);
        }

        // An `@` before the end of the authority — `/`, `\`, `?` or `#` — separates credentials, empty ones included.
        if (1 === preg_match('#^[Hh][Tt][Tt][Pp][Ss]?://[^/\\\\?\#]*@#', $text)) {
            throw InvalidUrl::credentials();
        }

        $parsed = WhatWgUrl::parse($text);

        if (!$parsed instanceof WhatWgUrl) {
            throw InvalidUrl::notAUrl($text);
        }

        if (0 === $parsed->getPort()) {
            throw InvalidUrl::port(0);
        }

        return $parsed;
    }

    /**
     * The address written back from its parts, the default port left out.
     */
    public static function write(string $scheme, string $host, ?int $port, string $path, ?string $query, ?string $fragment): string
    {
        return $scheme.'://'.$host
            .(null === $port ? '' : ':'.$port)
            .$path
            .(null === $query ? '' : '?'.$query)
            .(null === $fragment ? '' : '#'.$fragment);
    }

    /**
     * The canonical form, escapes included, against the 2048 characters.
     */
    public static function checkLength(string $written): void
    {
        if (\strlen($written) > Url::MAX_LENGTH) {
            throw InvalidUrl::tooLong(\strlen($written));
        }
    }

    public static function nonEmpty(?string $part): ?string
    {
        return '' === $part ? null : $part;
    }
}
