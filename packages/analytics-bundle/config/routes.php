<?php

declare(strict_types=1);

use Phil\AnalyticsBundle\Infrastructure\Http\CollectController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The collection endpoint, imported by the application under the prefix of its choice:
 *
 *     # config/routes/phil_analytics.yaml
 *     phil_analytics:
 *         resource: '@PhilAnalyticsBundle/config/routes.php'
 *         prefix: /analytics
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('phil_analytics_collect', '/collect')
        ->controller(CollectController::class)
        ->methods(['POST'])
        ->stateless();
};
