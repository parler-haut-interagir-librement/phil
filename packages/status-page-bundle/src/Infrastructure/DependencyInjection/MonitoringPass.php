<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\DependencyInjection;

use LogicException;
use Override;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\StatusPageBundle\Application\Contract\ComponentStatuses;
use Phil\StatusPageBundle\Infrastructure\Source\MonitoringStatuses;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Makes the checks of `phil/monitoring-bundle` the source of the components' states when its bundle is registered;
 * without it, the declared states stay the source, unless the configuration asked for "monitoring", which then fails.
 */
final readonly class MonitoringPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(MonitoringStatuses::class)) {
            return;
        }

        if ($container->has(MonitoringStatus::class)) {
            $container->setAlias(ComponentStatuses::class, MonitoringStatuses::class)->setPublic(true);

            return;
        }

        if ('monitoring' === $container->getParameter(StatusPageServices::SOURCE_PARAMETER)) {
            throw new LogicException('phil_status_page.source "monitoring" reads phil/monitoring-bundle: register PhilMonitoringBundle, or choose "declared".');
        }

        $container->removeDefinition(MonitoringStatuses::class);
    }
}
