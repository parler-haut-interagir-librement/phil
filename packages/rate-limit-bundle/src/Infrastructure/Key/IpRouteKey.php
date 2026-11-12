<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\Key;

use Override;
use Phil\RateLimitBundle\Application\Contract\KeyResolver;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Request;

/**
 * The `ip_route` strategy: the client's address and the route together, so that one limiter counts each route apart —
 * `ip_route:` and the HMAC-SHA-256, under the bundle's secret, of the address's key and the route's name, or of its
 * path when it has none.
 */
final readonly class IpRouteKey implements KeyResolver
{
    public function __construct(
        private KeyResolver $ip,
        #[SensitiveParameter]
        private string $secret,
    ) {
    }

    #[Override]
    public function keyOf(Request $request): ?string
    {
        $ip = $this->ip->keyOf($request);

        if (null === $ip) {
            return null;
        }

        $route = $request->attributes->get('_route');

        return 'ip_route:'.hash_hmac('sha256', $ip."\n".(\is_string($route) ? 'route:'.$route : 'path:'.$request->getPathInfo()), $this->secret);
    }
}
