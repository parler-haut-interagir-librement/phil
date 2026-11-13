<?php

declare(strict_types=1);

use Phil\PwaBundle\Infrastructure\Http\PwaController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The manifest at /manifest.webmanifest, the service worker at /sw.js and the offline page at /offline, imported by the
 * application without a prefix, so the worker controls the whole site:
 *
 *     # config/routes/phil_pwa.yaml
 *     phil_pwa:
 *         resource: '@PhilPwaBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(PwaController::MANIFEST_ROUTE, '/manifest.webmanifest')
        ->controller([PwaController::class, 'manifest'])
        ->methods(['GET', 'HEAD']);

    $routes->add(PwaController::SERVICE_WORKER_ROUTE, '/sw.js')
        ->controller([PwaController::class, 'serviceWorker'])
        ->methods(['GET', 'HEAD']);

    $routes->add(PwaController::OFFLINE_ROUTE, '/offline')
        ->controller([PwaController::class, 'offline'])
        ->methods(['GET', 'HEAD']);
};
