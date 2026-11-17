<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Override;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The scope of a request's keys: its verified tenant, its API client and its authenticated user, each when there is
 * one. Two requests share keys only when all three match: a user who acts in two tenants, or through two clients, has
 * keys of their own in each.
 *
 * A caller that is neither a user nor a client is refused with a `403` problem, unless `AnonymousCallers::ByIp` scopes
 * it by its address, within its tenant.
 *
 * The scope is `caller:` and the SHA-256 of these parts, each named and length-prefixed: it holds no identifier of
 * a user, and no choice of identifiers collides with another.
 */
final readonly class CallerScopeResolver implements IdempotencyScopeResolver
{
    /** The title of the `403` of an anonymous caller. */
    public const string ANONYMOUS = 'An Idempotency-Key is accepted from an authenticated caller only.';

    public function __construct(
        private ?TokenStorageInterface $tokens = null,
        private ?IdempotencyTenantResolver $tenants = null,
        private ?IdempotencyClientResolver $clients = null,
        private AnonymousCallers $anonymous = AnonymousCallers::Refuse,
    ) {
    }

    #[Override]
    public function scope(Request $request): IdempotencyScope
    {
        $parts = [];
        $tenant = $this->tenants?->tenant($request);
        $client = $this->clients?->client($request);
        $user = $this->tokens?->getToken()?->getUserIdentifier();

        if (null !== $tenant) {
            $parts[] = 'tenant';
            $parts[] = $tenant;
        }

        if (null !== $client) {
            $parts[] = 'client';
            $parts[] = $client;
        }

        if (null !== $user && '' !== $user) {
            $parts[] = 'user';
            $parts[] = $user;
        } elseif (null === $client) {
            if (AnonymousCallers::Refuse === $this->anonymous) {
                throw ProblemException::of(Response::HTTP_FORBIDDEN, self::ANONYMOUS);
            }

            $parts[] = 'ip';
            $parts[] = $request->getClientIp() ?? 'unknown';
        }

        return IdempotencyScope::of('caller:'.PayloadFingerprint::ofParts(...$parts)->hex);
    }
}
