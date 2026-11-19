<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Contract;

/**
 * Where the links sent by email point: the bundle's adapter puts them on `public_origin`, the canonical address of the
 * site, so that the `Host` header of the request that asked for a link never chooses where the link goes. An
 * application that serves several sites, one host per tenant, replaces it with one that knows the tenant's host.
 */
interface LinkOrigin
{
    /**
     * The absolute address `$generate` builds with the router, on the canonical origin.
     *
     * @param callable(): string $generate generates the absolute address of a route with the router of the application
     */
    public function link(callable $generate): string;
}
