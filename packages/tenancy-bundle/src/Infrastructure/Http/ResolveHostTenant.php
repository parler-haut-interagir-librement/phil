<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Http;

use Phil\TenancyBundle\Application\Contract\HostTenants;
use Phil\TenancyBundle\Application\Event\HostTenantResolved;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Enters the tenant a main request's host serves, before the firewall, so that authentication and every query of the
 * request act in it, then dispatches {@see HostTenantResolved}. A host of no tenant leaves the request to the other
 * resolutions. The host is read from the request as HttpFoundation reads it, after its trusted hosts.
 *
 * Without trusted hosts, any host a request names reaches the application, and the host decides the tenant: the first
 * request a host of a tenant serves is then logged as a warning, once per process — a worker serving many requests
 * warns once —, until `framework.trusted_hosts` lists the hosts.
 */
final class ResolveHostTenant
{
    /**
     * Whether this process has warned that `framework.trusted_hosts` is not set; kept from one request to the next on
     * purpose, and never reset.
     */
    private bool $warned = false;

    public function __construct(
        private readonly HostTenants $hosts,
        private readonly TenantContext $context,
        private readonly ?EventDispatcherInterface $dispatcher = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $host = $event->getRequest()->getHost();
        $tenant = $this->hosts->tenantFor($host);

        if (!$tenant instanceof TenantId) {
            return;
        }

        if (!$this->warned && [] === Request::getTrustedHosts()) {
            $this->warned = true;
            $this->logger?->warning('The host "{host}" serves the tenant "{tenant}", but framework.trusted_hosts is not set: any host a request names reaches the application. List the hosts of phil_tenancy.hosts in framework.trusted_hosts.', ['host' => $host, 'tenant' => $tenant->value]);
        }

        $this->context->enter($tenant);
        $this->dispatcher?->dispatch(new HostTenantResolved($host, $tenant));
    }
}
