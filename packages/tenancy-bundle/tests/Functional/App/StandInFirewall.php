<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\App;

use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Stands in for SecurityBundle's firewall, under its service id: it authenticates the account given in `X-Account`,
 * whose entitlements become `X-Reachable-Tenants`. It is registered far below the firewall's usual place, so that
 * only the bundle's ordering puts the tenant's resolution after it.
 */
final class StandInFirewall
{
    public function onRequest(RequestEvent $event): void
    {
        $headers = $event->getRequest()->headers;
        $account = $headers->get('X-Account');

        if (null !== $account) {
            $headers->set('X-Reachable-Tenants', $account);
        }
    }
}
