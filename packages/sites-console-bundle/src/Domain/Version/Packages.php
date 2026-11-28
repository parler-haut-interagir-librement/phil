<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Version;

/**
 * The names and versions of Composer packages, and their order.
 */
final readonly class Packages
{
    public const int VERSION_MAX_LENGTH = 64;

    private const string NAME_PATTERN = '/^[a-z0-9](?:[_.-]?[a-z0-9]+)*\/[a-z0-9](?:(?:[_.]|-{1,2})?[a-z0-9]+)*$/D';

    private const string VERSION_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._+\-\/]{0,63}$/D';

    public static function isName(string $name): bool
    {
        return \strlen($name) <= 214 && 1 === preg_match(self::NAME_PATTERN, $name);
    }

    public static function isVersion(string $version): bool
    {
        return 1 === preg_match(self::VERSION_PATTERN, $version);
    }

    /**
     * The order of two versions, `v` prefixes dropped: below 0 when the first is older, 0 when equal, above 0 when
     * newer; null when either is a branch (`dev-main`), which has no order.
     */
    public static function compare(string $first, string $second): ?int
    {
        $a = self::normalised($first);
        $b = self::normalised($second);

        if (null === $a || null === $b) {
            return null;
        }

        return version_compare($a, $b);
    }

    /**
     * Whether the name matches one of the patterns, where `*` stands for any characters: `vendor/*`, `*`.
     *
     * @param list<string> $patterns
     */
    public static function matches(string $name, array $patterns): bool
    {
        return array_any($patterns, static fn (string $pattern): bool => 1 === preg_match('/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/D', $name));
    }

    /**
     * Whether the version has an order: a release or a pre-release, not a branch.
     */
    public static function isOrdered(string $version): bool
    {
        return null !== self::normalised($version);
    }

    private static function normalised(string $version): ?string
    {
        $trimmed = 1 === preg_match('/^v\d/', $version) ? substr($version, 1) : $version;

        return 1 === preg_match('/^\d+(?:\.\d+)*(?:[-.+]?[A-Za-z0-9.]+)*$/D', $trimmed) ? $trimmed : null;
    }
}
