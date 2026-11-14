<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Http;

use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Application\Contract\HostAliases;
use Phil\TenancyBundle\Application\Contract\HostTenants;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * The hosts of `phil_tenancy.hosts`, each the tenant it serves. A host is written alone — no scheme, port or path —
 * and read as `phil/url` reads the host of an address: in lowercase, an international name in its ASCII form, so
 * that `Boutique.example` and `boutique.example` are one host and `bücher.example` matches the `xn--` form a
 * browser sends. An IP address is refused, and `localhost` is accepted for development. A host listed twice, once
 * read, is refused.
 *
 * The aliases of `phil_tenancy.host_aliases` each name the canonical host they stand for, one of the hosts, and serve
 * its tenant. An alias is read as a host is; one that is also a host, or listed twice, or that names a host that is not
 * one, is refused.
 */
final readonly class ConfiguredHostTenants implements HostTenants, HostAliases
{
    /** @var array<string, TenantId> */
    private array $tenants;

    /** @var array<string, string> the canonical host of each alias */
    private array $aliases;

    /**
     * @param array<array-key, string> $hosts   the tenant reference of each host
     * @param array<array-key, string> $aliases the canonical host of each alias
     *
     * @throws InvalidValue when a host, an alias or a reference is malformed, a host or an alias is listed twice, an
     *                      alias is a host, or an alias names a host that is not one
     */
    public function __construct(array $hosts, array $aliases = [])
    {
        $tenants = [];

        foreach ($hosts as $host => $reference) {
            $normalised = $this->host((string) $host);

            if (isset($tenants[$normalised])) {
                throw InvalidValue::because(\sprintf('The host "%s" is listed twice.', $normalised));
            }

            $tenants[$normalised] = TenantId::fromString($reference);
        }

        $canonical = [];

        foreach ($aliases as $alias => $host) {
            $normalised = $this->host((string) $alias);
            $target = $this->host($host);

            if (isset($tenants[$normalised])) {
                throw InvalidValue::because(\sprintf('The alias "%s" is a host of its own.', $normalised));
            }

            if (isset($canonical[$normalised])) {
                throw InvalidValue::because(\sprintf('The alias "%s" is listed twice.', $normalised));
            }

            if (!isset($tenants[$target])) {
                throw InvalidValue::because(\sprintf('The alias "%s" stands for "%s", which is not a host of a tenant.', $normalised, $target));
            }

            $canonical[$normalised] = $target;
        }

        $this->tenants = $tenants;
        $this->aliases = $canonical;
    }

    #[Override]
    public function tenantFor(string $host): ?TenantId
    {
        return $this->tenants[$this->aliases[$host] ?? $host] ?? null;
    }

    #[Override]
    public function canonicalHostOf(string $host): ?string
    {
        return $this->aliases[$host] ?? null;
    }

    private function host(string $host): string
    {
        try {
            $url = Url::fromString('https://'.$host.'/', allowLocalhost: true);
        } catch (InvalidUrl $invalidUrl) {
            throw InvalidValue::because(\sprintf('"%s" is not a host: %s', $host, $invalidUrl->getMessage()));
        }

        if (null !== $url->port || '/' !== $url->path || null !== $url->query || null !== $url->fragment) {
            throw InvalidValue::because(\sprintf('"%s" is not a host alone: write it with no port, path, query or fragment.', $host));
        }

        return $url->host;
    }
}
