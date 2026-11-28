<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\DependencyInjection;

use LogicException;
use Override;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\SitesConsoleBundle\Application\Contract\SiteHealth;
use Phil\SitesConsoleBundle\Infrastructure\Monitoring\MonitoringSiteHealth;
use Phil\SitesConsoleBundle\Infrastructure\Monitoring\UnknownSiteHealth;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Makes the checks of phil/monitoring-bundle the health the agent reports when its bundle is registered; without
 * it, the health is unknown, unless the configuration asked for "monitoring", which then fails.
 */
final readonly class MonitoringPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(SitesConsoleServices::HEALTH_PARAMETER)) {
            return;
        }

        $health = $container->getParameter(SitesConsoleServices::HEALTH_PARAMETER);

        if ('none' !== $health && $container->has(MonitoringStatus::class)) {
            $container->register(MonitoringSiteHealth::class, MonitoringSiteHealth::class)->setArguments([new Reference(MonitoringStatus::class)]);
            $container->setAlias(SiteHealth::class, MonitoringSiteHealth::class);

            return;
        }

        if ('monitoring' === $health) {
            throw new LogicException('phil_sites_console.agent.health "monitoring" reads phil/monitoring-bundle: register PhilMonitoringBundle, or choose "auto" or "none".');
        }

        $container->register(UnknownSiteHealth::class, UnknownSiteHealth::class);
        $container->setAlias(SiteHealth::class, UnknownSiteHealth::class);
    }
}
