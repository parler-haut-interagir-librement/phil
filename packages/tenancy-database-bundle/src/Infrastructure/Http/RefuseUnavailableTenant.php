<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Http;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantDatabaseUnavailable;
use Phil\TenancyDatabaseBundle\Infrastructure\Availability\TenantAvailabilityGuard;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Answers a request in a tenant that cannot be served now with a `503` and a `Retry-After`. Listened to twice: once
 * the host's tenant is entered, before the firewall reads an account from its database, and once the account's tenant
 * is resolved.
 */
final readonly class RefuseUnavailableTenant
{
    public function __construct(
        private TenantContext $context,
        private TenantAvailabilityGuard $guard,
        private int $retryAfter,
    ) {
    }

    /**
     * @throws ServiceUnavailableHttpException when the tenant of the request cannot be served now
     */
    public function onRequest(RequestEvent $event): void
    {
        $tenant = $this->context->current();

        if (!$event->isMainRequest() || !$tenant instanceof TenantId) {
            return;
        }

        try {
            $this->guard->check($tenant);
        } catch (TenantDatabaseUnavailable $tenantDatabaseUnavailable) {
            // The reason stays in the logs: the visitor learns only that the site is coming back.
            throw new ServiceUnavailableHttpException($this->retryAfter, 'This site is under maintenance; try again shortly.', $tenantDatabaseUnavailable);
        }
    }
}
