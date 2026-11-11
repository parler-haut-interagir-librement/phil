<?php

declare(strict_types=1);

namespace Phil\BounceParser\Reader;

/**
 * Addresses as reports write them.
 *
 * @internal
 */
final class Addresses
{
    private const string ADDRESS = '[A-Za-z0-9._%+=\'-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+';

    /**
     * The address of a field of a report, `rfc822; <Ada@Example.COM>`, its domain in lowercase, or `null`.
     */
    public static function ofTypedField(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $semicolon = strpos($value, ';');

        return self::normalise(false === $semicolon ? $value : substr($value, $semicolon + 1));
    }

    /**
     * The address of a header field, `Ada <ada@example.com>` or `ada@example.com`, or `null`.
     */
    public static function ofHeader(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return self::normalise(1 === preg_match('/<[^<>]*>/', $value, $match) ? $match[0] : $value);
    }

    /**
     * The first address in a text that is not a mail server's own (`MAILER-DAEMON`, `postmaster`), or `null`.
     */
    public static function firstIn(string $text): ?string
    {
        preg_match_all("/(?<![A-Za-z0-9._%+='-])".self::ADDRESS.'/', $text, $matches);

        foreach ($matches[0] as $address) {
            if (!self::isDaemon($address)) {
                return self::normalise($address);
            }
        }

        return null;
    }

    /**
     * Whether the address is a mail server's own: its local part is `MAILER-DAEMON`, `mail-daemon` or `postmaster`.
     */
    public static function isDaemon(string $address): bool
    {
        return 1 === preg_match('/^(?:mailer-daemon|mail-daemon|postmaster)(?:@|$)/i', $address);
    }

    private static function normalise(string $address): ?string
    {
        $text = trim($address, " \t<>");
        $at = strrpos($text, '@');

        if (false === $at || 0 === $at || \strlen($text) - 1 === $at) {
            return null;
        }

        return substr($text, 0, $at).'@'.strtolower(substr($text, $at + 1));
    }
}
