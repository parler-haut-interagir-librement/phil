<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Messenger;

use Override;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantMiddleware;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantDatabaseUnavailable;
use Phil\TenancyDatabaseBundle\Infrastructure\Availability\TenantAvailabilityGuard;
use Symfony\Component\Messenger\Attribute\AsMessageMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Refuses to handle a received message in a tenant that cannot be served now: {@see TenantDatabaseUnavailable} is
 * thrown, and the retry strategy of the transport tries again later.
 *
 * On every bus, after the tenant middleware of `phil/tenancy-bundle`, which enters the tenant of the message, and in
 * front of the middleware that opens, pings or closes a Doctrine connection: an application whose bus configuration
 * lists it keeps it where the configuration puts it.
 */
#[AsMessageMiddleware(bus: '*', before: TenantMiddleware::BEFORE, after: TenantMiddleware::class)]
final readonly class RefuseUnavailableTenantMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TenantContext $context,
        private TenantAvailabilityGuard $guard,
    ) {
    }

    #[Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $tenant = $this->context->current();

        if ($envelope->last(ReceivedStamp::class) instanceof StampInterface && $tenant instanceof TenantId) {
            $this->guard->check($tenant);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
