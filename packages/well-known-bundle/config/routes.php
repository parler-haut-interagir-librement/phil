<?php

declare(strict_types=1);

use Phil\WellKnownBundle\Infrastructure\Routing\WellKnownRouteLoader;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The routes of the well-known documents the configuration turns on, imported by the application:
 *
 *     # config/routes/phil_well_known.yaml
 *     phil_well_known:
 *         resource: '@PhilWellKnownBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('.', WellKnownRouteLoader::TYPE);
};
