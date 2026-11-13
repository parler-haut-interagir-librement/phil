<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Domain\ValueObject;

use Phil\PwaBundle\Domain\Exception\InvalidManifest;
use Uri\WhatWg\Url;

/**
 * The checks the members of a manifest go through: one-line texts, local paths, asset paths, colours and tags.
 *
 * @internal
 */
final readonly class Rule
{
    public const int MAX_PATH = 2048;

    /** The origin local paths are resolved against: any address that leaves it was not local. */
    private const string ORIGIN = 'https://pwa.invalid';

    /**
     * One line of 1 to `$length` characters of UTF-8, its spaces collapsed, without control characters.
     *
     * @return non-empty-string
     */
    public static function line(string $value, string $member, int $length): string
    {
        // Text that is not UTF-8 makes preg_replace() return null, which is the empty line refused below.
        $line = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ('' === $line || mb_strlen($line) > $length || 1 === preg_match('/[\x00-\x1F\x7F]/', $line)) {
            throw InvalidManifest::because(\sprintf('The %s is one line of 1 to %d characters, without control characters.', $member, $length));
        }

        return $line;
    }

    /**
     * A path of the site, from its root — `/`, `/app/?source=pwa` —, as a browser resolves it: dot segments removed,
     * characters percent-encoded. A path that leaves the origin (`//host`, `/\host`), carries a fragment, or holds
     * spaces or control characters is refused.
     *
     * @return non-empty-string
     */
    public static function localPath(string $value, string $member): string
    {
        if (!str_starts_with($value, '/') || \strlen($value) > self::MAX_PATH || 1 === preg_match('/[\x00-\x20\x7F\\\\#]/', $value)) {
            throw self::notLocal($member);
        }

        $url = Url::parse($value, Url::parse(self::ORIGIN.'/'));

        // A path resolved to `//…`, such as `/.//host`, would be read as another host where it is written back.
        if (!$url instanceof Url || self::ORIGIN !== 'https://'.$url->getAsciiHost() || str_starts_with($url->getPath(), '//')) {
            throw self::notLocal($member);
        }

        $query = $url->getQuery();

        return '/'.substr($url->getPath(), 1).(null === $query ? '' : '?'.$query);
    }

    /**
     * Whether `$path` is `$scope` or under it, as a browser compares a URL with the scope of a service worker.
     */
    public static function isWithin(string $path, string $scope): bool
    {
        return str_starts_with($path, $scope);
    }

    /**
     * The logical path of an asset, as AssetMapper or the Asset component know it — `images/icon-192.png`,
     * `@phil/pwa-bundle/service_worker.js` —, without a `..` segment.
     *
     * @return non-empty-string
     */
    public static function assetPath(string $value, string $member): string
    {
        if (1 !== preg_match('#^[A-Za-z0-9@_][A-Za-z0-9@_.+~-]*(?:/[A-Za-z0-9@_.+~-]+)*$#D', $value) || \strlen($value) > self::MAX_PATH || 1 === preg_match('#(?:^|/)\.\.?(?:/|$)#', $value)) {
            throw InvalidManifest::because(\sprintf('The %s is a path of the site from its root, or the logical path of an asset without "." or ".." segments.', $member));
        }

        return $value;
    }

    /**
     * A local path when it starts with `/`, else the logical path of an asset.
     *
     * @return non-empty-string
     */
    public static function source(string $value, string $member): string
    {
        return str_starts_with($value, '/') ? self::localPath($value, $member) : self::assetPath($value, $member);
    }

    /**
     * A colour: `#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`, or a CSS colour keyword.
     *
     * @return non-empty-string
     */
    public static function colour(string $value, string $member): string
    {
        if (1 !== preg_match('/^(?:#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|[a-z]{3,20})$/D', $value)) {
            throw InvalidManifest::because(\sprintf('The %s is a hexadecimal colour, such as #1b1b1b, or a CSS colour keyword.', $member));
        }

        return $value;
    }

    private static function notLocal(string $member): InvalidManifest
    {
        return InvalidManifest::because(\sprintf('The %s is a path of the site from its root, such as /app/, without fragment, spaces or control characters.', $member));
    }
}
