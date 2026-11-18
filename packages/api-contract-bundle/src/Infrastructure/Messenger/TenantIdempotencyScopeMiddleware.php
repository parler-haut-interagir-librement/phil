<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Messenger;

use Override;
use Phil\Idempotency\Messenger\IdempotencyStamp;
use Phil\Idempotency\PayloadFingerprint;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Puts the tenant of a received envelope's `TenantStamp` into the scope of its `IdempotencyStamp`, so that the
 * idempotency middleware of `phil/idempotency`, placed after this one on the bus, handles a key once per tenant: two
 * tenants that dispatch a message under the same key and scope are both handled, and a message replayed in one tenant
 * is handled once.
 *
 * The scope becomes {@see self::SCOPE_PREFIX} and the SHA-256 of the tenant's reference and the scope the producer
 * gave, each named and length-prefixed: bounded, and no choice of tenant and scope collides with another. The envelope
 * is marked with a {@see TenantScopedIdempotencyStamp}, so that a retry, which carries the scoped stamp, is not scoped
 * again; an envelope marked for another tenant than its `TenantStamp` names is refused, without retry.
 *
 * An envelope that is not received, or that lacks either stamp, passes through as it is. The middleware holds no state:
 * a worker that handles messages of many tenants scopes each one by its own stamp.
 */
final readonly class TenantIdempotencyScopeMiddleware implements MiddlewareInterface
{
    public const string SCOPE_PREFIX = 'tenant:';

    #[Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $idempotency = $envelope->last(IdempotencyStamp::class);
        $tenant = $envelope->last(TenantStamp::class);

        if (!$envelope->last(ReceivedStamp::class) instanceof StampInterface || !$idempotency instanceof IdempotencyStamp || !$tenant instanceof TenantStamp) {
            return $stack->next()->handle($envelope, $stack);
        }

        $scoped = $envelope->last(TenantScopedIdempotencyStamp::class);

        if ($scoped instanceof TenantScopedIdempotencyStamp) {
            if ($scoped->tenant !== $tenant->tenant) {
                throw new UnrecoverableMessageHandlingException('The idempotency key of this message was scoped for another tenant than the one it is handled in.');
            }

            return $stack->next()->handle($envelope, $stack);
        }

        $envelope = $envelope
            ->withoutAll(IdempotencyStamp::class)
            ->with(self::scoped($idempotency, $tenant->tenant), new TenantScopedIdempotencyStamp($tenant->tenant));

        return $stack->next()->handle($envelope, $stack);
    }

    /**
     * The stamp of the same key and fingerprint, in the scope of the tenant.
     */
    public static function scoped(IdempotencyStamp $stamp, string $tenant): IdempotencyStamp
    {
        return new IdempotencyStamp(
            $stamp->key,
            self::SCOPE_PREFIX.PayloadFingerprint::ofParts('tenant', $tenant, 'scope', $stamp->scope)->hex,
            null === $stamp->fingerprint ? null : PayloadFingerprint::fromHex($stamp->fingerprint),
        );
    }
}
