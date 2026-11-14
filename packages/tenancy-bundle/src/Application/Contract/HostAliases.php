<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Application\Contract;

/**
 * The other names of a tenant's host: `www.` in front of it, an old domain, a domain of the platform. An alias serves
 * the tenant of its canonical host, and can send its visitors there.
 */
interface HostAliases
{
    /**
     * @param string $host the host of the request, in lowercase and in its ASCII form, as HttpFoundation reads it
     *
     * @return ?string the canonical host the alias stands for, in the same form, or `null` for a host that is no alias
     */
    public function canonicalHostOf(string $host): ?string;
}
