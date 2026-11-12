<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Stringable;
use Uri\WhatWg\Url;

/**
 * A web origin, `scheme://host[:port]`, normalized: lower-case scheme and host, default port dropped.
 *
 * A collection request belongs to the site whose declared origin it carries. The origin is read from the
 * `Origin` header, or failing that from the `Referer` ({@see self::ofRequest()}), and anything else is refused
 * without a write. Only `http` and `https` are origins here: a page view never comes from anything else.
 *
 * The value is read as a browser reads it, by PHP's WHATWG URL parser on which `phil/url` rests: an international host
 * in its ASCII form, as browsers send it in `Origin`, an IPv6 address in brackets, a backslash in a `Referer` taken for
 * the slash that ends the host. Any host a browser can be on is an origin, `localhost` and an IP address included: the
 * declared origins decide which ones collect. User information, even empty, is refused.
 */
final readonly class Origin implements Stringable
{
    private function __construct(
        public string $scheme,
        public string $host,
        public ?int $port,
    ) {
    }

    /**
     * Strict form, as declared by the application or sent in an `Origin` header: no path, query, fragment or
     * credentials.
     */
    public static function fromString(string $value): self
    {
        $origin = self::parse($value, strict: true);

        if (!$origin instanceof self) {
            throw InvalidValue::because(\sprintf('"%s" is not an http or https origin.', $value));
        }

        return $origin;
    }

    /**
     * The origin of a full URL, such as a `Referer`; `null` when the URL has no http or https origin.
     */
    public static function ofUrl(string $url): ?self
    {
        return self::parse($url, strict: false);
    }

    /**
     * The origin a collection request claims: its `Origin` header, or failing that the origin of its `Referer`.
     * An opaque `Origin: null` is no origin, and the `Referer` is then consulted. `null` means the request names
     * no origin at all and must be refused.
     */
    public static function ofRequest(?string $originHeader, ?string $referer): ?self
    {
        if (null !== $originHeader && '' !== $originHeader && 'null' !== $originHeader) {
            return self::parse($originHeader, strict: true);
        }

        if (null !== $referer && '' !== $referer) {
            return self::ofUrl($referer);
        }

        return null;
    }

    public function equals(self $other): bool
    {
        return $this->scheme === $other->scheme && $this->host === $other->host && $this->port === $other->port;
    }

    public function toString(): string
    {
        return $this->scheme.'://'.$this->host.(null === $this->port ? '' : ':'.$this->port);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function parse(string $value, bool $strict): ?self
    {
        // The host starts right after the two slashes, and an `@` before the end of the authority — `/`, `\`, `?` or
        // `#` — introduces credentials; the strict form is the authority alone.
        $shape = $strict ? '#^https?://[^/\\\\?\#@\x00-\x20\x7F]+$#iD' : '#^https?://[^/\\\\?\#@\x00-\x20\x7F]+(?:[/\\\\?\#][^\x00-\x20\x7F]*)?$#iD';

        if (1 !== preg_match($shape, $value)) {
            return null;
        }

        $url = Url::parse($value);

        if (!$url instanceof Url) {
            return null;
        }

        return new self($url->getScheme(), (string) $url->getAsciiHost(), $url->getPort());
    }
}
