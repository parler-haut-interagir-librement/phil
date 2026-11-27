<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Messenger;

use Override;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Carries the active tenant across a transport: a message dispatched in a tenant is stamped with it, and a
 * message received from a transport is handled in the tenant of its stamp, then the worker returns to the tenant
 * it was in. A message received without a stamp is handled in the worker's tenant — usually none, where the
 * Doctrine filter fails closed.
 *
 * On every bus, in front of the middleware that opens, pings or closes a Doctrine connection, so that the connection is
 * the tenant's: an application whose bus configuration lists it keeps it where the configuration puts it.
 */
#[AsMessageMiddleware(bus: '*', before: self::BEFORE)]
final readonly class TenantMiddleware implements MiddlewareInterface
{
    /**
     * The middleware of Symfony's Doctrine bridge this one runs before, as a bus configuration names them.
     */
    public const array BEFORE = ['doctrine_ping_connection', 'doctrine_close_connection', 'doctrine_open_transaction_logger', 'doctrine_transaction'];

    public function __construct(
        private TenantContext $context,
    ) {
    }

    #[Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(TenantStamp::class);

        if (!$envelope->last(ReceivedStamp::class) instanceof StampInterface) {
            $tenant = $this->context->current();

            if (!$stamp instanceof StampInterface && $tenant instanceof TenantId) {
                $envelope = $envelope->with(new TenantStamp($tenant->value));
            }

            return $stack->next()->handle($envelope, $stack);
        }

        if (!$stamp instanceof StampInterface) {
            return $stack->next()->handle($envelope, $stack);
        }

        return $this->context->within(TenantId::fromString($stamp->tenant), static fn (): Envelope => $stack->next()->handle($envelope, $stack));
    }
}
