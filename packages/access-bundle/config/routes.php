<?php

declare(strict_types=1);

use Phil\AccessBundle\Infrastructure\Http\ViewAsController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The forms of "view as this member", imported by the application under a prefix of its choice, behind its firewall:
 *
 *     # config/routes/phil_access.yaml
 *     phil_access:
 *         resource: '@PhilAccessBundle/config/routes.php'
 *         prefix: /_access
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('phil_access_view_as_stop', '/view-as/stop')->controller([ViewAsController::class, 'stop'])->methods(['POST']);
    $routes->add('phil_access_view_as_start', '/view-as/start/{member}')
        ->controller([ViewAsController::class, 'start'])
        ->methods(['POST'])
        ->requirements(['member' => '[A-Za-z0-9][A-Za-z0-9_.-]{0,127}']);
};
