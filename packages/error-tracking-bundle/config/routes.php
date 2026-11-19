<?php

declare(strict_types=1);

use Phil\ErrorTrackingBundle\Infrastructure\Http\AdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The administration pages of the groups, imported by the application under the prefix of its choice:
 *
 *     phil_error_tracking:
 *         resource: '@PhilErrorTrackingBundle/config/routes.php'
 *         prefix: /admin/errors
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(AdminController::INDEX_ROUTE, '/')
        ->controller([AdminController::class, 'index'])
        ->methods(['GET']);
    $routes->add(AdminController::SHOW_ROUTE, '/{fingerprint}')
        ->controller([AdminController::class, 'show'])
        ->requirements(['fingerprint' => AdminController::FINGERPRINT])
        ->methods(['GET']);
    $routes->add(AdminController::STATUS_ROUTE, '/{fingerprint}/{action}')
        ->controller([AdminController::class, 'status'])
        ->requirements(['fingerprint' => AdminController::FINGERPRINT, 'action' => AdminController::ACTION])
        ->methods(['POST']);
};
