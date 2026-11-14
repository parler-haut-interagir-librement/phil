<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Cache;

use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * The sub-namespace and the tag prefix of a tenant in a cache pool scoped to the active tenant.
 *
 * A reference of at most {@see self::READABLE_LENGTH} letters, digits, hyphens and underscores stays readable:
 * `tenant.acme`. Any other reference — longer, or with a character a cache key cannot hold or that would make two
 * references alike — becomes the hexadecimal SHA-256 of the reference: `tenant-` followed by 64 characters. The two
 * forms never meet, one after a dot and the other after a hyphen, and a readable reference holds no dot, so two tenants
 * never share a namespace or a tag prefix; neither form holds a character reserved in a cache key.
 *
 * The global namespace, when no tenant is active, is the pool itself, with tags unchanged. The names that start with
 * `tenant.` or `tenant-` belong to the tenants: a sub-namespace or a tag of that form is refused, so that nothing
 * written in the global namespace lands in a tenant's.
 */
final class TenantCacheNamespace
{
    public const string PREFIX = 'tenant';

    public const int READABLE_LENGTH = 64;

    private const string READABLE = '/^[A-Za-z0-9_-]{1,64}$/D';

    private function __construct()
    {
    }

    /**
     * The tenant's sub-namespace: `tenant.<reference>`, or `tenant-<digest>`.
     */
    public static function of(TenantId $tenant): string
    {
        if (1 === preg_match(self::READABLE, $tenant->value)) {
            return self::PREFIX.'.'.$tenant->value;
        }

        return self::PREFIX.'-'.hash('sha256', $tenant->value);
    }

    /**
     * What every tag of the tenant is prefixed with: its sub-namespace and a dot; nothing in the global namespace.
     */
    public static function tagPrefix(?TenantId $tenant): string
    {
        return $tenant instanceof TenantId ? self::of($tenant).'.' : '';
    }

    public static function isReserved(string $name): bool
    {
        return str_starts_with($name, self::PREFIX.'.') || str_starts_with($name, self::PREFIX.'-');
    }

    /**
     * @throws InvalidArgumentException when the name is a tenant's
     */
    public static function refuseReserved(string $name, string $what): string
    {
        if (self::isReserved($name)) {
            throw new InvalidArgumentException(\sprintf('The %s "%s" starts with "%s." or "%s-", which the tenants\' namespaces use.', $what, $name, self::PREFIX, self::PREFIX));
        }

        return $name;
    }
}
