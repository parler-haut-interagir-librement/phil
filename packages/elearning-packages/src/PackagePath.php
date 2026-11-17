<?php

declare(strict_types=1);

namespace Phil\ElearningPackages;

use Phil\ElearningPackages\Exception\PackageRefused;

/**
 * The path of a file inside a package, checked before it reaches the storage: relative, `/`-separated, in UTF-8,
 * without `.` or `..` segments, empty segments, backslashes, colons or control characters, and bounded in length and
 * depth. A path that would leave the package — `../`, `/etc`, `C:\` — is refused, never cleaned up.
 */
final class PackagePath
{
    public const int MAX_LENGTH = 1024;

    public const int MAX_SEGMENTS = 32;

    private function __construct()
    {
    }

    /**
     * @throws PackageRefused when the path is not one a file of the package has
     */
    public static function check(string $path): string
    {
        if ('' === $path || \strlen($path) > self::MAX_LENGTH || !mb_check_encoding($path, 'UTF-8')) {
            throw PackageRefused::because(\sprintf('A path in a package is 1 to %d bytes of UTF-8.', self::MAX_LENGTH));
        }

        if (1 === preg_match('/[\x00-\x1F\x7F\\\\:]/', $path) || str_starts_with($path, '/')) {
            throw PackageRefused::because(\sprintf('The path "%s" is absolute, or holds a backslash, a colon or a control character.', self::shown($path)));
        }

        $segments = explode('/', $path);

        if (\count($segments) > self::MAX_SEGMENTS) {
            throw PackageRefused::because(\sprintf('The path "%s" is deeper than %d directories.', self::shown($path), self::MAX_SEGMENTS));
        }

        foreach ($segments as $segment) {
            if ('' === $segment || '.' === $segment || '..' === $segment) {
                throw PackageRefused::because(\sprintf('The path "%s" holds an empty, "." or ".." segment.', self::shown($path)));
            }
        }

        return $path;
    }

    /**
     * The file a manifest's reference names: the reference read as a relative URI — its query and fragment set
     * apart, its escapes decoded, its `.` segments dropped — against the bases given, then checked.
     *
     * @param list<string> $bases the `xml:base` values that apply, outermost first
     *
     * @return array{0: string, 1: ?string} the path, and what followed it: `?query`, `#fragment`, or null
     *
     * @throws PackageRefused when the reference leaves the package, or is not a path
     */
    public static function resolve(array $bases, string $reference): array
    {
        $suffix = null;

        if (1 === preg_match('/[?#]/', $reference, $match, \PREG_OFFSET_CAPTURE)) {
            $suffix = substr($reference, $match[0][1]);
            $reference = substr($reference, 0, $match[0][1]);
        }

        // A base's leading or trailing `/` makes an empty segment, which is dropped with the `.` segments.
        $joined = implode('/', [...$bases, $reference]);
        $segments = array_filter(explode('/', rawurldecode($joined)), static fn (string $segment): bool => '' !== $segment && '.' !== $segment);

        return [self::check(implode('/', $segments)), $suffix];
    }

    /**
     * Whether the reference is an absolute web address rather than a file of the package.
     */
    public static function isWebAddress(string $reference): bool
    {
        return 1 === preg_match('#^https?://#i', $reference);
    }

    private static function shown(string $path): string
    {
        return mb_substr((string) preg_replace('/[\x00-\x1F\x7F]/', '?', mb_scrub($path, 'UTF-8')), 0, 120);
    }
}
