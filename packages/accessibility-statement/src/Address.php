<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

use Phil\AccessibilityStatement\Exception\InvalidStatement;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * The addresses a statement links to, checked once: an `https://` address, or a path of the site itself — never
 * `javascript:`, another scheme, or a path that leaves for another host.
 *
 * An `https://` address is read by `phil/url`, as a browser reads it: an international host is accepted, and an address
 * with a user name or a password, an IP address, `localhost` or a single name for its host is refused. The address is
 * kept as it was written.
 */
final class Address
{
    public const int MAX_LENGTH = 2048;

    private function __construct()
    {
    }

    /**
     * An `https://` address, or a path of the site when `$path` allows one.
     */
    public static function check(string $address, string $field, bool $path = true): string
    {
        $local = $path && 1 === preg_match('~^/(?![/\\\\])~', $address);
        $https = str_starts_with(strtolower($address), 'https://') && self::isWebAddress($address);

        if (\strlen($address) > self::MAX_LENGTH || 1 === preg_match('/[\x00-\x20\x7F]/', $address) || (!$local && !$https)) {
            throw InvalidStatement::because('address', \sprintf('The %s is an https:// address%s of at most %d bytes.', $field, $path ? ' or a path of the site' : '', self::MAX_LENGTH));
        }

        return $address;
    }

    private static function isWebAddress(string $address): bool
    {
        try {
            Url::fromString($address);

            return true;
        } catch (InvalidUrl) {
            return false;
        }
    }
}
