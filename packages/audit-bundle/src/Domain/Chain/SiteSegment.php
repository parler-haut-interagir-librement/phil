<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Chain;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The segment of one site — one tenant — of a trail shared by several: the segment the application names, followed
 * by `.t-` and the first 32 hexadecimal digits — 128 bits — of the SHA-256 of the tenant reference. Each site then has
 * a chain of its own, anchored and verified on its own, and the name says which tenant every row of it must carry,
 * which verification checks: a row of another site moved into it shows.
 *
 * 128 bits keep two tenants from sharing a segment even when the references are chosen by users: naming the segment of
 * a given tenant takes about 2^128 tries, and two references of the same segment, both chosen, about 2^64. The digest
 * takes no key: the name of a segment is hashed into each of its rows, so it never changes, whatever secret the
 * application rotates, and anyone holding the trail can check which tenant a segment belongs to.
 *
 * The tenant reference itself is not in the name — it may be long, or hold characters a segment does not —; the rows
 * carry it, as their tenant fact.
 */
final class SiteSegment
{
    /** What separates the segment the application names from the digest of the tenant. */
    public const string MARK = '.t-';

    /** The hexadecimal digits of the digest: 128 bits. */
    public const int DIGEST_LENGTH = 32;

    /** The longest segment name a site segment can be built on, so that the whole fits in 64 characters. */
    public const int MAX_BASE_LENGTH = 64 - 3 - self::DIGEST_LENGTH;

    private function __construct()
    {
    }

    /**
     * The segment of that tenant.
     *
     * @throws InvalidValue when the segment is longer than {@see self::MAX_BASE_LENGTH}, is a site segment already, or
     *                      the tenant is not a reference
     */
    public static function of(string $segment, string $tenant): string
    {
        Facts::assertTenant($tenant);

        if (\strlen($segment) > self::MAX_BASE_LENGTH || null !== self::digestOf($segment)) {
            throw InvalidValue::because(\sprintf('A site segment is built on a segment of at most %d characters that is not a site segment; "%s" is not.', self::MAX_BASE_LENGTH, $segment));
        }

        return $segment.self::MARK.self::digest($tenant);
    }

    /**
     * Whether the segment is a site segment, of any tenant.
     */
    public static function isSite(string $segment): bool
    {
        return null !== self::digestOf($segment);
    }

    /**
     * Whether the segment is the site segment of that tenant.
     */
    public static function belongsTo(string $segment, string $tenant): bool
    {
        $digest = self::digestOf($segment);

        return null !== $digest && hash_equals($digest, self::digest($tenant));
    }

    /**
     * The segment the application named, without the digest of the site.
     */
    public static function base(string $segment): string
    {
        return self::isSite($segment) ? substr($segment, 0, -(\strlen(self::MARK) + self::DIGEST_LENGTH)) : $segment;
    }

    /**
     * The digest a site segment ends with, `null` for a segment that is not one.
     */
    private static function digestOf(string $segment): ?string
    {
        return 1 === preg_match('/^.+\.t-([0-9a-f]{32})$/D', $segment, $parts) ? $parts[1] : null;
    }

    private static function digest(string $tenant): string
    {
        return substr(hash('sha256', $tenant), 0, self::DIGEST_LENGTH);
    }
}
