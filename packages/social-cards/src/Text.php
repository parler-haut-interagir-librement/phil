<?php

declare(strict_types=1);

namespace Phil\SocialCards;

use Phil\SocialCards\Exception\InvalidShareMetadata;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url as PublicUrl;
use Uri\WhatWg\Url;

/**
 * The text and addresses the tags carry, made safe for one line of a `content` attribute: whitespace and control
 * characters collapsed into single spaces, the invisible formatting characters removed, the text cut on a character
 * boundary, the addresses checked.
 *
 * @internal
 */
final class Text
{
    public const string ELLIPSIS = '…';

    /** The longest address a value may hold, as the sitemap protocol and most crawlers allow. */
    public const int MAX_URL = 2048;

    /** The most characters of a value an exception quotes. */
    public const int EXCERPT = 100;

    /**
     * The text on one line, at most `$max` characters — the last one an ellipsis when it was cut —, or null when
     * nothing but blanks is left. The invisible formatting characters (`\p{Cf}`) are removed — the controls of the
     * direction of the text, which turn `moc.elpmaxe` into `example.com` on screen, the zero-width spaces, the soft
     * hyphen —, but for the zero-width joiner and non-joiner, which shape an emoji or a word.
     */
    public static function line(?string $text, int $max): ?string
    {
        if (null === $text) {
            return null;
        }

        $text = trim((string) preg_replace(['/(?![\x{200C}\x{200D}])\p{Cf}/u', '/[\s\p{Cc}\p{Zl}\p{Zp}]+/u'], ['', ' '], self::utf8($text)));

        if ('' === $text) {
            return null;
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).self::ELLIPSIS;
    }

    /**
     * The beginning of a value for the message of an exception: at most `EXCERPT` characters.
     */
    public static function excerpt(string $value): string
    {
        return mb_substr(self::utf8($value), 0, self::EXCERPT);
    }

    /**
     * The text as valid UTF-8, each invalid byte replaced.
     */
    private static function utf8(string $text): string
    {
        return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    /**
     * An absolute HTTP or HTTPS address without credentials, as given once trimmed.
     */
    public static function absoluteUrl(string $url, string $field): string
    {
        $url = trim($url);

        if (!self::isAbsoluteUrl($url)) {
            throw InvalidShareMetadata::because(\sprintf('The %s must be an absolute HTTP or HTTPS address without credentials, of %d characters at most; "%s" is not.', $field, self::MAX_URL, self::excerpt($url)));
        }

        return $url;
    }

    public static function isAbsoluteUrl(string $url): bool
    {
        if ('' === $url || \strlen($url) > self::MAX_URL || !mb_check_encoding($url, 'UTF-8') || 1 === preg_match('/[\s\p{Cc}]/u', $url)) {
            return false;
        }

        $parsed = Url::parse($url);

        return $parsed instanceof Url
            && \in_array($parsed->getScheme(), ['http', 'https'], true)
            && '' === (string) $parsed->getUsername()
            && '' === (string) $parsed->getPassword();
    }

    /**
     * An address read in a page, resolved against the page's own, then read by phil/url: an absolute HTTP or HTTPS
     * address on a public host name, in its canonical form, or null.
     */
    public static function resolve(?string $value, string $base): ?string
    {
        $value = self::line($value, self::MAX_URL);

        if (null === $value) {
            return null;
        }

        $baseUrl = Url::parse($base);
        $resolved = $baseUrl instanceof Url ? Url::parse($value, $baseUrl) : Url::parse($value);

        return $resolved instanceof Url ? self::publicUrl($resolved->toAsciiString()) : null;
    }

    /**
     * An address read by phil/url, in its canonical form, or null: an absolute HTTP or HTTPS address without
     * credentials, whose host is a public name — never an IP address, `localhost`, a name under `.localhost` nor a
     * single label such as `intranet` —, 2,048 characters at most.
     */
    public static function publicUrl(string $url): ?string
    {
        try {
            return PublicUrl::fromString($url)->toString();
        } catch (InvalidUrl) {
            return null;
        }
    }

    /**
     * Whether two addresses that `publicUrl()` accepts name the same host and the same port.
     */
    public static function onSameHost(string $url, string $other): bool
    {
        $url = PublicUrl::fromString($url);
        $other = PublicUrl::fromString($other);

        return $url->host === $other->host && $url->port === $other->port;
    }
}
