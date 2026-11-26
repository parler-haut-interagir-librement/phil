<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Application;

use Phil\CorsBundle\Domain\CorsPolicy;
use Symfony\Component\HttpFoundation\Request;

/**
 * Finds the CORS policy of a request, or none when the bundle leaves it alone. The bundle's own reads the paths of its
 * configuration; an application aliases this interface to its own service to choose by host, by tenant or by anything
 * else the request carries. An implementation keeps no state between requests.
 */
interface PolicyResolver
{
    public function resolve(Request $request): ?CorsPolicy;
}
