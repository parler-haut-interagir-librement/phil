<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\Key;

use Override;
use Phil\RateLimitBundle\Application\Contract\KeyResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * The `user_or_ip` strategy: the signed-in user's key, else the client's address's.
 */
final readonly class UserOrIpKey implements KeyResolver
{
    public function __construct(
        private KeyResolver $user,
        private KeyResolver $ip,
    ) {
    }

    #[Override]
    public function keyOf(Request $request): ?string
    {
        return $this->user->keyOf($request) ?? $this->ip->keyOf($request);
    }
}
