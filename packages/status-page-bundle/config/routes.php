<?php

declare(strict_types=1);

use Phil\StatusPageBundle\Infrastructure\Http\StatusPageController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The status page at /status, its JSON at /status.json and its Atom feed at /status.atom, imported by the application:
 *
 *     # config/routes/phil_status_page.yaml
 *     phil_status_page:
 *         resource: '@PhilStatusPageBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(StatusPageController::ROUTE, '/status')
        ->controller([StatusPageController::class, 'page'])
        ->methods(['GET', 'HEAD']);

    $routes->add(StatusPageController::JSON_ROUTE, '/status.json')
        ->controller([StatusPageController::class, 'json'])
        ->methods(['GET', 'HEAD']);

    $routes->add(StatusPageController::ATOM_ROUTE, '/status.atom')
        ->controller([StatusPageController::class, 'atom'])
        ->methods(['GET', 'HEAD']);
};
