<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Override;
use Phil\Idempotency\IdempotencyScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The authenticated user's identifier, `user:…`; without one, the client's address, `ip:…`. An application whose
 * callers are API clients replaces it with its own resolver.
 */
final readonly class CallerScopeResolver implements IdempotencyScopeResolver
{
    public function __construct(
        private ?TokenStorageInterface $tokens = null,
    ) {
    }

    #[Override]
    public function scope(Request $request): IdempotencyScope
    {
        $user = $this->tokens?->getToken()?->getUserIdentifier();

        return IdempotencyScope::of(null !== $user && '' !== $user ? 'user:'.$user : 'ip:'.($request->getClientIp() ?? 'unknown'));
    }
}
