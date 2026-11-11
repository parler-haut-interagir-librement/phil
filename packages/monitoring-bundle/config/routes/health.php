<?php

declare(strict_types=1);

use Phil\MonitoringBundle\Infrastructure\Http\HealthController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The endpoints of the uptime monitors, imported by the application where it wants them:
 *
 *     # config/routes/phil_monitoring.yaml
 *     phil_monitoring_health:
 *         resource: '@PhilMonitoringBundle/config/routes/health.php'
 *         prefix: /_health
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(HealthController::LIVE_ROUTE, '/live')
        ->controller([HealthController::class, 'live'])
        ->methods(['GET', 'HEAD']);
    $routes->add(HealthController::READY_ROUTE, '/ready')
        ->controller([HealthController::class, 'ready'])
        ->methods(['GET', 'HEAD']);
    $routes->add(HealthController::DETAILS_ROUTE, '/details')
        ->controller([HealthController::class, 'details'])
        ->methods(['GET']);
};
