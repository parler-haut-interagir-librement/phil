<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Messenger;

use Override;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantDatabaseUnavailable;
use Phil\TenancyDatabaseBundle\Infrastructure\Availability\TenantAvailabilityGuard;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Refuses to handle a received message in a tenant that cannot be served now: {@see TenantDatabaseUnavailable} is
 * thrown, and the retry strategy of the transport tries again later. Placed after the tenant middleware of
 * `phil/tenancy-bundle`, which enters the tenant of the message.
 */
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
