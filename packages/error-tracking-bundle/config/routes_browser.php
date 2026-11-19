<?php

declare(strict_types=1);

use Phil\ErrorTrackingBundle\Infrastructure\Http\BrowserErrorController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The endpoint of the browsers' errors, `POST /_errors/browser`, stateless: imported by the application when
 * phil_error_tracking.browser is enabled, which may add a prefix.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(BrowserErrorController::ROUTE, '/_errors/browser')
        ->controller(BrowserErrorController::class)
        ->methods(['POST'])
        ->stateless();
};
