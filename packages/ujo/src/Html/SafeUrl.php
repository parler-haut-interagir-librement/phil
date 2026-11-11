<?php

declare(strict_types=1);

namespace Phil\Ujo\Html;

/**
 * Whether an address may be written in a link or a source: no space, control character or backslash; a scheme from a
 * list, with a host for `http` and `https`, a number for `tel` and `sms` and coordinates for `geo`; or, when allowed, a
 * relative address that is not protocol-relative — a path, a query or a fragment of the same page.
 */
final class SafeUrl
{
    /** The schemes of the messengers whose addresses are not web addresses: XMPP, Matrix and Viber. */
    public const array MESSENGERS = ['xmpp', 'matrix', 'viber'];

    /** The schemes of a link: the web, e-mail, telephone, text message, a place on Earth, and the messengers. */
    public const array LINK = ['http', 'https', 'mailto', 'tel', 'sms', 'geo', ...self::MESSENGERS];

    /** The schemes of an image, a file or a frame. */
    public const array MEDIA = ['http', 'https'];

    /** The form of the addresses of the schemes that have one, after the scheme and its colon. */
    private const array FORMS = [
        'http' => '~^//[^/?#@]+(?:[/?#]|$)~',
        'https' => '~^//[^/?#@]+(?:[/?#]|$)~',
        'tel' => '/^\+?[0-9][0-9().-]{0,31}(?:;[A-Za-z0-9=;._%+-]{1,200})?$/D',
        'sms' => '/^\+?[0-9][0-9().-]{0,31}(?:[,;?][A-Za-z0-9=;&,._%+-]{1,1000})?$/D',
        'geo' => '/^-?\d{1,2}(?:\.\d{1,12})?,-?\d{1,3}(?:\.\d{1,12})?(?:,-?\d{1,6}(?:\.\d{1,6})?)?(?:;[A-Za-z0-9=;._-]{1,200})?$/D',
    ];

    /**
     * @param list<string> $schemes in lower case
     */
    public static function isSafe(string $url, array $schemes = self::LINK, bool $relative = true): bool
    {
        if (1 === preg_match('/^$|[\x00-\x20\x7F\\\\]/', $url)) {
            return false;
        }

        $scheme = self::scheme($url);

        if (null === $scheme) {
            return $relative && !str_starts_with($url, '//');
        }

        return \in_array($scheme, $schemes, true)
            && (!isset(self::FORMS[$scheme]) || 1 === preg_match(self::FORMS[$scheme], substr($url, \strlen($scheme) + 1)));
    }

    /**
     * The scheme of an address in lower case; `null` for a relative address.
     */
    public static function scheme(string $url): ?string
    {
        return 1 === preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $url, $match) ? strtolower($match[1]) : null;
    }
}
