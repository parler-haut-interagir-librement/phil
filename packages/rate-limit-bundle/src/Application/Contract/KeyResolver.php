<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application\Contract;

use Symfony\Component\HttpFoundation\Request;

/**
 * Who a request counts for: the key a limiter counts it under.
 *
 * The bundle gives four — `ip`, `user`, `user_or_ip` and `ip_route` —, each a keyed hash. An application gives its own
 * — an account, a tenant, an API key's identifier — as a service implementing this interface, named by its service id.
 * A key longer than 200 bytes is replaced by its SHA-256.
 */
interface KeyResolver
{
    /**
     * @return string|null null when the request has no key for this strategy, and the limiter does not count it
     */
    public function keyOf(Request $request): ?string;
}
